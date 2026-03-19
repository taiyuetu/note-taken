<?php

declare(strict_types=1);

require_once __DIR__ . '/app.php';

function get_env_var(string $name, $default = false)
{
    $val = getenv($name);
    if ($val === false && isset($_ENV[$name])) {
        $val = $_ENV[$name];
    }
    if ($val === false && isset($_SERVER[$name])) {
        $val = $_SERVER[$name];
    }

    return $val !== false ? $val : $default;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = get_env_var('DB_HOST', '127.0.0.1');
    $port = get_env_var('DB_PORT', '3306');
    $name = get_env_var('DB_NAME', 'notes');
    $user = get_env_var('DB_USER', 'root');
    $pass = get_env_var('DB_PASS', '');

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
