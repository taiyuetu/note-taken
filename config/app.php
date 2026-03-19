<?php

declare(strict_types=1);

// FORCE ENABLE ERROR DISPLAY FOR TROUBLESHOOTING
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    // If you see this output, please update your PHP to at least 7.4 or 8.1
    // exit('Your PHP version is ' . PHP_VERSION . '. Please update to PHP 8.1+ for stability.');
}

// PHP 8.0+ Polyfills
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle)
    {
        return (string) $needle === '' || strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle)
    {
        $l = strlen($needle);
        return $l === 0 || substr($haystack, -$l) === (string) $needle;
    }
}

// mbstring Polyfills
if (!function_exists('mb_strlen')) {
    function mb_strlen($str)
    {
        return strlen($str);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null)
    {
        return substr($str, $start, $length);
    }
}

function load_dotenv(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $separator = strpos($trimmed, '=');

        if ($separator === false) {
            continue;
        }

        $name = trim(substr($trimmed, 0, $separator));
        $value = trim(substr($trimmed, $separator + 1));

        if ($name === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) !== false) {
            continue;
        }

        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

load_dotenv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

date_default_timezone_set('Asia/Shanghai');

const APP_NAME = 'Notes Taken';
const NOTE_ATTACHMENT_MAX_BYTES = 10485760;

function app_invite_code(): string
{
    return (string) (getenv('APP_INVITE_CODE') ?: '');
}

function configured_app_url(): string
{
    return rtrim((string) (getenv('APP_URL') ?: ''), '/');
}
