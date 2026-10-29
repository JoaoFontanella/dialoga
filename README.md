# Dialoga

## Requisitos

Para Windows ou Linux:

- Node.js com npm.
- Python 3.7 ou superior.
- PHP 7.4 ou superior com a extensão `pdo_pgsql` habilitada.
- PostgreSQL em execução, com o banco importado de `database/selfhealing_db.sql`.

Os executáveis devem estar disponíveis no `PATH`. No Windows, o Python também pode ser localizado pelo launcher `py`.

## Configuração do .env

Crie ou edite o arquivo `.env` na raiz do projeto:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=selfhealing_db
DB_USERNAME=postgres
DB_PASSWORD="sua_senha"
DB_SSLMODE=prefer
```

Preencha com as credenciais do seu PostgreSQL. O `.env` é local e está ignorado pelo Git.

## Rodar

Dentro da pasta `dialoga`, execute:

```bash
npm start
```

Caso necessário execute `npm install`. Acesse [http://localhost:8080](http://localhost:8080).

Para encerrar, pressione **Ctrl+C** no mesmo terminal. Após alterar o `.env`, reinicie o servidor.
