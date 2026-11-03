<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH.'third_party/dialoga/env.php';

// erp: somente leitura (dialoga_leitor) · historico: perguntas, tentativas de IA e dicionário
$active_group  = 'erp';
$query_builder = TRUE;

$dialoga_grupo = function ($prefixo, $base_padrao) {
    $host = dialoga_env($prefixo.'_DB_HOST', '127.0.0.1');
    $porta = dialoga_env($prefixo.'_DB_PORT', '5432');
    $base = dialoga_env($prefixo.'_DB_DATABASE', $base_padrao);

    return array(
        'dsn'          => 'pgsql:host='.$host.';port='.$porta.';dbname='.$base.';connect_timeout=5',
        'hostname'     => $host,
        'port'         => $porta,
        'username'     => dialoga_env($prefixo.'_DB_USERNAME', ''),
        'password'     => dialoga_env($prefixo.'_DB_PASSWORD', ''),
        'database'     => $base,
        'dbdriver'     => 'pdo',
        'subdriver'    => 'pgsql',
        'dbprefix'     => '',
        'pconnect'     => FALSE,
        'db_debug'     => FALSE,   // erros viram retorno FALSE; a aplicação decide o que mostrar
        'cache_on'     => FALSE,
        'cachedir'     => '',
        'char_set'     => 'utf8',
        'dbcollat'     => '',
        'swap_pre'     => '',
        'encrypt'      => FALSE,
        'compress'     => FALSE,
        'stricton'     => FALSE,
        'failover'     => array(),
        'save_queries' => FALSE,
    );
};

$db['erp']       = $dialoga_grupo('ERP', 'selfhealing_db');
$db['historico'] = $dialoga_grupo('HIST', 'dialoga_historico');
unset($dialoga_grupo);
