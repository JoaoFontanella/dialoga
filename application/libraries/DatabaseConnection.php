<?php
namespace Dialoga\Infrastructure;

/** Configuração compartilhada pelo PDO e pelo futuro CodeIgniter 3. */
final class DatabaseConnection
{
    private static $connection;

    public static function settings()
    {
        $file = dirname(__DIR__, 2) . '/.env';
        $values = array();
        if (is_file($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            if ($lines === false) {
                throw new \RuntimeException('Não foi possível ler a configuração do banco.');
            }
            foreach ($lines as $index => $line) {
                $line = trim($line);
                if ($line === '' || strpos($line, '#') === 0) {
                    continue;
                }
                if (!preg_match('/^([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $match)) {
                    throw new \RuntimeException('Formato inválido no .env, linha ' . ($index + 1) . '.');
                }
                $value = trim($match[2]);
                if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                    if (strlen($value) < 2 || substr($value, -1) !== $value[0]) {
                        throw new \RuntimeException('Aspas inválidas no .env, linha ' . ($index + 1) . '.');
                    }
                    $value = substr($value, 1, -1);
                }
                $values[$match[1]] = $value;
            }
        }
        $get = function ($key, $default = '') use ($values) {
            $environment = getenv($key);
            return $environment !== false ? $environment : (isset($values[$key]) ? $values[$key] : $default);
        };
        $config = array(
            'host' => $get('DB_HOST', '127.0.0.1'),
            'port' => $get('DB_PORT', '5432'),
            'database' => $get('DB_DATABASE', 'selfhealing_db'),
            'username' => $get('DB_USERNAME'),
            'password' => $get('DB_PASSWORD'),
            'sslmode' => $get('DB_SSLMODE', 'prefer')
        );
        if ($config['host'] === '' || preg_match('/[;\x00-\x20]/', $config['host']) ||
            !preg_match('/^[a-zA-Z0-9_.-]+$/', $config['database'])) {
            throw new \RuntimeException('DB_HOST ou DB_DATABASE inválido no .env.');
        }
        if (!ctype_digit((string) $config['port']) || (int) $config['port'] < 1 || (int) $config['port'] > 65535) {
            throw new \RuntimeException('DB_PORT deve ser uma porta válida.');
        }
        if (!in_array($config['sslmode'], array('disable', 'allow', 'prefer', 'require', 'verify-ca', 'verify-full'), true)) {
            throw new \RuntimeException('DB_SSLMODE inválido.');
        }
        if ($config['username'] === '') {
            throw new \RuntimeException('Preencha DB_USERNAME no .env.');
        }
        return $config;
    }

    public static function dsn(array $config)
    {
        return 'pgsql:host=' . $config['host'] . ';port=' . $config['port'] .
            ';dbname=' . $config['database'] . ';sslmode=' . $config['sslmode'] . ';connect_timeout=5';
    }

    public static function connect()
    {
        if (self::$connection instanceof \PDO) {
            return self::$connection;
        }
        if (!extension_loaded('pdo_pgsql')) {
            throw new \RuntimeException('Habilite a extensão pdo_pgsql do PHP.');
        }
        $config = self::settings();
        try {
            self::$connection = new \PDO(self::dsn($config), $config['username'], $config['password'], self::options());
        } catch (\PDOException $exception) {
            throw new \RuntimeException('Não foi possível conectar ao PostgreSQL. Confira o serviço e as credenciais do .env.');
        }
        return self::$connection;
    }

    public static function options()
    {
        return array(
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES => false,
            \PDO::ATTR_PERSISTENT => false
        );
    }
}
