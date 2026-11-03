# Dialoga · MVP

Pergunte. Seu negócio responde.

O gestor pergunta em português sobre as vendas; a Dialoga responde com o número, a origem e, quando não sabe, diz que não sabe. Stack: CodeIgniter 3.1.13, AngularJS 1.8, PostgreSQL e Gemini.

## Como funciona

```
pergunta ─► Gemini (cadeia de modelos) ─► interpretação em JSON ─► validação ─► SQL fixo e parametrizado ─► resposta
                 └─ todos falharam ─► regras locais ─┘
```

- O Gemini só interpreta. Não escreve SQL nem calcula números; os textos com números são montados no PHP a partir do banco.
- Quatro perguntas: total por período, vendas por produto, ranking e comparação. O resto vira "não sei" ou "uma pergunta antes".
- Rollback de modelos como no TCC: 429, 404, 5xx, timeout ou JSON inválido passam ao próximo modelo; o modelo que falhou fica em pausa por um tempo. Sem nenhum modelo, as regras locais interpretam e a consulta continua real.
- Dois bancos: o ERP de demonstração, lido por um usuário somente leitura, e o histórico (perguntas, respostas, cada tentativa de modelo com tokens e latência, e o dicionário vivo).

## Requisitos

- PHP 5.4 ou superior com `pdo_pgsql`, `curl`, `mbstring` e `openssl`
- PostgreSQL 9.6 ou superior
- Para `npm start`: Node.js e Python 3.7+ (ou rode o servidor do PHP direto, abaixo)

Em PHP 5.4 antigo, confira se o cURL fala TLS 1.2 com a API do Google; se der erro de certificado, aponte `GEMINI_CA_BUNDLE` para um `cacert.pem` atual.

## Instalação

1. ERP de demonstração. O arquivo `database/selfhealing_db.sql` está no formato custom do `pg_dump`:

   ```bash
   createdb selfhealing_db
   pg_restore -d selfhealing_db --no-owner database/selfhealing_db.sql
   psql -d selfhealing_db -f database/01_erp_demo.sql
   ```

   O script cria as filiais, a data de faturamento, 13 meses de vendas até ontem e o usuário `dialoga_leitor`. Rode na véspera do pitch e troque a senha do usuário (`ALTER ROLE dialoga_leitor PASSWORD '...'`).

2. Histórico:

   ```bash
   createdb dialoga_historico
   psql -d dialoga_historico -f database/02_historico.sql
   ```

3. Copie `.env.example` para `.env` e preencha. A chave gratuita do Gemini sai em https://aistudio.google.com/app/apikey.

4. Confira os modelos que a sua chave enxerga:

   ```bash
   php index.php ferramentas modelos
   ```

5. Suba:

   ```bash
   npm start
   # ou
   php -S 127.0.0.1:8080 -t . tools/router.php
   ```

   Acesse http://localhost:8080. Para ver erros do PHP na tela, use `CI_ENV=development`.

## API

| Método | Rota | Uso |
|---|---|---|
| GET | `index.php/api/status` | cobertura da base, período padrão, persona |
| POST | `index.php/api/perguntar` | `{pergunta, contexto, conversa_id}` → `{tipo, texto, destaque, origem, barras, tabela, acoes, contexto, conversa_id, via, tempo_ms}` |
| GET | `index.php/api/historico` | últimas perguntas deste navegador |
| GET | `index.php/api/conversa/{id}` | turnos de uma conversa |

`tipo` é `resposta`, `recusa` ou `esclarecer`. O histórico é separado por navegador (cookie `dialoga_cliente`).

## Onde mexer

| Arquivo | O quê |
|---|---|
| `application/prompts/interpretador.md` | prompt do Gemini |
| `application/config/dialoga.php` | cadeia de modelos, prazos, período padrão, persona |
| `application/models/Vendas_model.php` | métricas ("faturadas" e "fechados") e consultas permitidas |
| `application/libraries/Respondedor.php` | o que cada intenção responde |
| `application/libraries/Interpretador_local.php` | regras usadas sem IA |
| tabela `dicionario` | termos da empresa; o gestor ensina "vendas = faturadas" no primeiro esclarecimento |

## Métricas para o plano de testes

```sql
SELECT * FROM v_metricas_diarias;  -- perguntas, recusas, tempo médio e p95, tokens por pergunta
SELECT * FROM v_modelos;           -- sucesso, quota esgotada e latência por modelo
```

## Para o pitch

- Para a demonstração começar respondendo direto, sem perguntar "faturadas ou fechados?", insira a linha `vendas` no dicionário (comentada em `02_historico.sql`).
- Para zerar o que o gestor ensinou: `DELETE FROM dicionario WHERE origem = 'gestor';`
- No plano gratuito do Gemini, o Google pode usar o conteúdo enviado para melhorar os modelos. Com a base de demonstração não há problema; com dados reais de uma empresa parceira, use o plano pago.
