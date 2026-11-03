-- Dialoga · ERP de demonstração a partir da base do TCC (selfhealing_db).
-- Gera 13 meses de vendas até ontem em 3 filiais. Rode na véspera do pitch.
-- A base do repositório está em formato custom: restaure com pg_restore antes.

BEGIN;

CREATE TABLE IF NOT EXISTS public.filiais (
    id     SERIAL PRIMARY KEY,
    slug   VARCHAR(30) NOT NULL UNIQUE,
    nome   VARCHAR(60) NOT NULL,
    cidade VARCHAR(60) NOT NULL,
    ativa  BOOLEAN NOT NULL DEFAULT true
);
COMMENT ON TABLE public.filiais IS 'Filiais da distribuidora de demonstração';

INSERT INTO public.filiais (slug, nome, cidade) VALUES
    ('centro',    'Centro',    'Criciúma'),
    ('tubarao',   'Tubarão',   'Tubarão'),
    ('ararangua', 'Araranguá', 'Araranguá')
ON CONFLICT (slug) DO NOTHING;

ALTER TABLE public.pedidos ADD COLUMN IF NOT EXISTS filial_id   INTEGER REFERENCES public.filiais(id);
ALTER TABLE public.pedidos ADD COLUMN IF NOT EXISTS faturado_em TIMESTAMP WITHOUT TIME ZONE;
COMMENT ON COLUMN public.pedidos.faturado_em IS 'Data de emissão da nota. Nula enquanto o pedido não foi faturado';

CREATE INDEX IF NOT EXISTS idx_pedidos_criado_em   ON public.pedidos (criado_em);
CREATE INDEX IF NOT EXISTS idx_pedidos_faturado_em ON public.pedidos (faturado_em);
CREATE INDEX IF NOT EXISTS idx_pedidos_filial_id   ON public.pedidos (filial_id);

TRUNCATE public.pagamentos, public.itens_pedido, public.pedidos RESTART IDENTITY CASCADE;

DO $$
DECLARE
    -- uma semana antes do mês, para o primeiro mês ter todas as notas
    inicio   DATE := (date_trunc('month', current_date) - interval '13 months')::date - 7;
    fim      DATE := current_date - 1;
    dia      DATE;
    fil      RECORD;
    prod     RECORD;
    cli      RECORD;
    fator    NUMERIC;
    n        INT;
    itens    INT;
    pid      INT;
    sorteio  NUMERIC;
    situacao TEXT;
    criado   TIMESTAMP;
    faturado TIMESTAMP;
BEGIN
    PERFORM setseed(0.42);

    FOR dia IN SELECT generate_series(inicio, fim, interval '1 day')::date LOOP
        FOR fil IN SELECT id, slug, cidade FROM public.filiais ORDER BY id LOOP

            fator := CASE fil.slug WHEN 'centro' THEN 1.35 WHEN 'tubarao' THEN 1.0 ELSE 0.7 END
                   * CASE extract(isodow FROM dia) WHEN 6 THEN 0.45 WHEN 7 THEN 0.10 ELSE 1 END
                   * CASE extract(month FROM dia)
                         WHEN 11 THEN 1.20 WHEN 12 THEN 1.40 WHEN 1 THEN 0.80
                         WHEN 2 THEN 0.85 WHEN 7 THEN 0.95 ELSE 1 END
                   * (0.92 + 0.16 * (dia - inicio)::numeric / (fim - inicio));

            n := floor(random() * 6 * fator + 2 * fator)::int;

            FOR k IN 1..n LOOP
                SELECT id, cidade, estado INTO cli
                  FROM public.clientes WHERE ativo ORDER BY random() LIMIT 1;

                criado := dia + make_interval(hours => 8 + floor(random() * 10)::int,
                                              mins  => floor(random() * 60)::int);
                faturado := criado + make_interval(days => 1 + floor(random() * 3)::int);
                IF extract(isodow FROM faturado) = 7 THEN
                    faturado := faturado + interval '1 day';
                END IF;
                sorteio  := random();

                IF sorteio < 0.04 THEN
                    situacao := 'cancelado'; faturado := NULL;
                ELSIF sorteio < 0.10 AND current_date - dia <= 4 THEN
                    situacao := 'pendente';  faturado := NULL;
                ELSIF faturado::date >= current_date THEN
                    situacao := 'confirmado'; faturado := NULL;
                ELSIF current_date - faturado::date <= 4 THEN
                    situacao := 'enviado';
                ELSE
                    situacao := 'entregue';
                END IF;

                INSERT INTO public.pedidos
                       (cliente_id, filial_id, status, total, endereco_entrega, criado_em, atualizado_em, faturado_em)
                VALUES (cli.id, fil.id, situacao, 0, 'Entrega a partir da filial ' || fil.cidade,
                        criado, COALESCE(faturado, criado), faturado)
                RETURNING id INTO pid;

                itens := 1 + floor(random() * 3)::int;
                FOR prod IN
                    SELECT id, preco FROM public.produtos WHERE ativo
                     ORDER BY -ln(random()) * sqrt(preco) LIMIT itens
                LOOP
                    INSERT INTO public.itens_pedido (pedido_id, produto_id, quantidade, preco_unit)
                    VALUES (pid, prod.id,
                            CASE WHEN prod.preco < 600 THEN 1 + floor(random() * 4)::int
                                 ELSE 1 + floor(random() * 1.3)::int END,
                            prod.preco);
                END LOOP;

                UPDATE public.pedidos p
                   SET total = (SELECT sum(subtotal) FROM public.itens_pedido WHERE pedido_id = pid)
                 WHERE p.id = pid;

                IF faturado IS NOT NULL THEN
                    INSERT INTO public.pagamentos (pedido_id, metodo, valor, status, transacao_id, pago_em)
                    SELECT pid,
                           (ARRAY['pix','boleto','cartao','transferencia'])[1 + floor(random() * 4)::int],
                           total, 'aprovado', 'TXN-' || lpad(pid::text, 6, '0'), faturado
                      FROM public.pedidos WHERE id = pid;
                END IF;
            END LOOP;
        END LOOP;
    END LOOP;
END $$;

ALTER TABLE public.pedidos ALTER COLUMN filial_id SET NOT NULL;
ANALYZE public.pedidos, public.itens_pedido, public.pagamentos;

COMMIT;

-- "Só lê" garantido pelo banco. Troque a senha e use a mesma em ERP_DB_PASSWORD.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'dialoga_leitor') THEN
        CREATE ROLE dialoga_leitor LOGIN PASSWORD 'troque-esta-senha';
    END IF;
END $$;

DO $$
BEGIN
    EXECUTE format('GRANT CONNECT ON DATABASE %I TO dialoga_leitor', current_database());
END $$;
GRANT USAGE ON SCHEMA public TO dialoga_leitor;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO dialoga_leitor;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO dialoga_leitor;
ALTER ROLE dialoga_leitor SET default_transaction_read_only = on;
ALTER ROLE dialoga_leitor SET statement_timeout = '5s';

SELECT f.nome AS filial,
       to_char(date_trunc('month', p.faturado_em), 'YYYY-MM') AS mes,
       count(*) AS pedidos,
       round(sum(p.total) / 1000) AS mil_reais
  FROM public.pedidos p JOIN public.filiais f ON f.id = p.filial_id
 WHERE p.status IN ('enviado', 'entregue')
   AND p.faturado_em >= date_trunc('month', current_date) - interval '2 months'
 GROUP BY 1, 2 ORDER BY 2, 1;
