<?php
// Ponte mínima de conexão. Os endpoints de perguntas serão implementados no CI3.
ini_set('display_errors', '0');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/' || $path === '/index.php') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/index.html');
    return;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($path !== '/index.php/api/status') {
    http_response_code(404);
    echo json_encode(array('erro' => 'Endpoint não implementado.'));
    return;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    echo json_encode(array('erro' => 'Método não permitido.'));
    return;
}

try {
    require_once __DIR__ . '/application/libraries/DatabaseConnection.php';
    $pdo = \Dialoga\Infrastructure\DatabaseConnection::connect();
    $pdo->exec('BEGIN READ ONLY');
    $pdo->exec("SET LOCAL statement_timeout = '5s'");
    $row = $pdo->query('SELECT CURRENT_DATE::text AS hoje, MIN(criado_em)::date::text AS inicio, MAX(criado_em)::date::text AS fim FROM public.pedidos')->fetch();
    $pdo->commit();
    echo json_encode(array(
        'modo' => 'postgres',
        'hoje' => $row['hoje'],
        'base' => array('inicio' => $row['inicio'], 'fim' => $row['fim']),
        'consultas_disponiveis' => false
    ), JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(503);
    echo json_encode(array('erro' => 'Não foi possível conectar à base de vendas. Verifique o serviço PostgreSQL, o .env e a tabela pedidos.'), JSON_UNESCAPED_UNICODE);
}
