-- Dialoga · banco de histórico (createdb dialoga_historico)

BEGIN;

CREATE TABLE IF NOT EXISTS perguntas (
    id              BIGSERIAL PRIMARY KEY,
    cliente         VARCHAR(64),
    conversa        VARCHAR(40),
    pergunta        TEXT        NOT NULL,
    contexto        JSONB,
    interpretacao   JSONB,
    via             VARCHAR(80) NOT NULL,        -- modelo Gemini, 'regras-locais' ou 'acao'
    tipo            VARCHAR(12) NOT NULL
                    CHECK (tipo IN ('resposta', 'recusa', 'esclarecer', 'erro')),
    resposta        JSONB,
    tempo_total_ms  INTEGER,
    tempo_ia_ms     INTEGER,
    tokens_entrada  INTEGER NOT NULL DEFAULT 0,
    tokens_saida    INTEGER NOT NULL DEFAULT 0,
    criado_em       TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_perguntas_criado_em ON perguntas (criado_em DESC);
CREATE INDEX IF NOT EXISTS idx_perguntas_conversa  ON perguntas (cliente, conversa, id);

CREATE TABLE IF NOT EXISTS tentativas_ia (
    id             BIGSERIAL PRIMARY KEY,
    pergunta_id    BIGINT REFERENCES perguntas(id) ON DELETE CASCADE,
    ordem          SMALLINT    NOT NULL,
    modelo         VARCHAR(80) NOT NULL,
    resultado      VARCHAR(30) NOT NULL,
    http_status    SMALLINT,
    latencia_ms    INTEGER,
    tokens_entrada INTEGER,
    tokens_saida   INTEGER,
    detalhe        TEXT,
    criado_em      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_tentativas_pergunta ON tentativas_ia (pergunta_id);

CREATE TABLE IF NOT EXISTS dicionario (
    id          SERIAL PRIMARY KEY,
    termo       VARCHAR(80)  NOT NULL UNIQUE,
    significado TEXT         NOT NULL,
    tipo        VARCHAR(20)  NOT NULL CHECK (tipo IN ('metrica', 'sinonimo', 'produto', 'filial')),
    valor       VARCHAR(80),
    origem      VARCHAR(20)  NOT NULL DEFAULT 'implantacao' CHECK (origem IN ('implantacao', 'gestor')),
    criado_em   TIMESTAMPTZ  NOT NULL DEFAULT now(),
    alterado_em TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- "vendas" fica de fora: a Dialoga pergunta "faturadas ou fechados?" e grava a escolha do gestor.
INSERT INTO dicionario (termo, significado, tipo, valor) VALUES
    ('faturamento',  'vendas faturadas: pedidos com nota emitida, pela data da nota',        'metrica',  'faturadas'),
    ('nota',         'vendas faturadas',                                                     'metrica',  'faturadas'),
    ('pedido fechado','pedidos confirmados, enviados ou entregues, pela data do pedido',      'metrica',  'fechados'),
    ('carteira',     'pedidos fechados',                                                     'metrica',  'fechados'),
    ('loja do centro','filial Centro, em Criciúma',                                          'filial',   'centro'),
    ('matriz',       'filial Centro, em Criciúma',                                           'filial',   'centro')
    -- ,('vendas', 'quando o gestor diz vendas ou vendemos, quer vendas faturadas', 'metrica', 'faturadas')
ON CONFLICT (termo) DO NOTHING;

CREATE OR REPLACE VIEW v_metricas_diarias AS
SELECT date_trunc('day', p.criado_em)::date                          AS dia,
       count(*)                                                       AS perguntas,
       count(*) FILTER (WHERE p.tipo = 'resposta')                    AS respostas,
       count(*) FILTER (WHERE p.tipo = 'recusa')                      AS recusas,
       count(*) FILTER (WHERE p.tipo = 'esclarecer')                  AS esclarecimentos,
       count(*) FILTER (WHERE p.via = 'regras-locais')                AS sem_ia,
       round(avg(p.tempo_total_ms))                                   AS tempo_medio_ms,
       percentile_cont(0.95) WITHIN GROUP (ORDER BY p.tempo_total_ms) AS tempo_p95_ms,
       round(avg(p.tokens_entrada + p.tokens_saida))                  AS tokens_medios
  FROM perguntas p
 GROUP BY 1
 ORDER BY 1 DESC;

CREATE OR REPLACE VIEW v_modelos AS
SELECT modelo,
       count(*)                                      AS tentativas,
       count(*) FILTER (WHERE resultado = 'sucesso') AS sucessos,
       count(*) FILTER (WHERE resultado = 'quota')   AS quota_esgotada,
       round(avg(latencia_ms) FILTER (WHERE resultado = 'sucesso')) AS latencia_media_ms
  FROM tentativas_ia
 GROUP BY modelo
 ORDER BY tentativas DESC;

COMMIT;

-- CREATE ROLE dialoga_app LOGIN PASSWORD 'troque-esta-senha';
-- GRANT USAGE ON SCHEMA public TO dialoga_app;
-- GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA public TO dialoga_app;
-- GRANT USAGE ON ALL SEQUENCES IN SCHEMA public TO dialoga_app;
