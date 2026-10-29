<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once dirname(__DIR__) . '/libraries/DatabaseConnection.php';

$connection = \Dialoga\Infrastructure\DatabaseConnection::settings();
$active_group = 'default';
$query_builder = true;

// O futuro CI3 consumirá as mesmas credenciais do .env, usando PDO PostgreSQL.
$db['default'] = array(
    'dsn' => \Dialoga\Infrastructure\DatabaseConnection::dsn($connection),
    'hostname' => $connection['host'],
    'username' => $connection['username'],
    'password' => $connection['password'],
    'database' => $connection['database'],
    'dbdriver' => 'pdo',
    'subdriver' => 'pgsql',
    'dbprefix' => '',
    'pconnect' => false,
    'db_debug' => false,
    'cache_on' => false,
    'cachedir' => '',
    'char_set' => 'utf8',
    'dbcollat' => '',
    'swap_pre' => '',
    'encrypt' => false,
    'compress' => false,
    'stricton' => false,
    'failover' => array(),
    'save_queries' => false,
    'options' => \Dialoga\Infrastructure\DatabaseConnection::options()
);
unset($connection);
