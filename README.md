# Dialoga

Site para consultar dados de vendas por meio de perguntas em português, apresentando respostas com contexto e origem dos dados.

O frontend está implementado. **Ainda falta desenvolver o backend em PHP com CodeIgniter 3 e integrar o PostgreSQL.** Até essa integração, é possível enviar mensagens e visualizá-las na conversa, mas não há respostas.

## Tecnologias

- **Frontend:** HTML5, CSS3, JavaScript e AngularJS 1.8.3.
- **Backend previsto:** PHP com CodeIgniter 3.
- **Banco de dados:** PostgreSQL.
- **Execução local do frontend:** servidor HTTP do Python 3.

## Como rodar

No terminal, entre na pasta `dialoga` e execute:

```bash
python3 -m http.server 8080 --bind 127.0.0.1
```

Acesse [http://localhost:8080/](http://localhost:8080/).

Para parar o servidor, pressione **Ctrl+C** no mesmo terminal. Esse comando serve o frontend; o backend PHP ainda não está disponível.

## Banco de dados

O banco já está exportado em [database/selfhealing_db.sql](database/selfhealing_db.sql), com estrutura e dados, pronto para importar em um banco PostgreSQL vazio.
