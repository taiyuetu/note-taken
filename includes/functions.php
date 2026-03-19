<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function app_url(string $path = ''): string
{
    $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

    if ($basePath === '' || $basePath === '.') {
        $basePath = '';
    }

    return $basePath . '/' . ltrim($path, '/');
}

function absolute_app_url(string $path = ''): string
{
    $configured = configured_app_url();

    if ($configured !== '') {
        return $configured . '/' . ltrim($path, '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . app_url($path);
}

function redirect(string $path): void
{
    header('Location: ' . app_url($path));
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf'];
}

function verify_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';

    if (!hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function old(string $key, string $default = ''): string
{
    return $_SESSION['_old'][$key] ?? $default;
}

function store_old_input(array $input): void
{
    $_SESSION['_old'] = $input;
}

function clear_old_input(): void
{
    unset($_SESSION['_old']);
}

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function current_user(): ?array
{
    $userId = current_user_id();

    if ($userId === null) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, username, email, created_at FROM users WHERE id = :id');
    $stmt->execute(['id' => $userId]);

    $user = $stmt->fetch();

    return $user ?: null;
}

function require_guest(): void
{
    if (current_user_id() !== null) {
        redirect('dashboard.php');
    }
}

function require_auth(): void
{
    if (current_user_id() === null) {
        flash('warning', 'Please sign in first.');
        redirect('login.php');
    }
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }

    return (new DateTimeImmutable($value))->format('Y-m-d H:i');
}

function format_bytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    $units = ['KB', 'MB', 'GB', 'TB'];
    $value = $bytes / 1024;

    foreach ($units as $unit) {
        if ($value < 1024 || $unit === end($units)) {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . ' ' . $unit;
        }

        $value /= 1024;
    }

    return $bytes . ' B';
}

function excerpt(?string $html, int $limit = 140): string
{
    $text = trim(strip_tags((string) $html));

    if (mb_strlen($text) <= $limit) {
        return $text;
    }

    return mb_substr($text, 0, $limit - 3) . '...';
}

function clean_html(string $html): string
{
    $allowed = '<p><br><strong><em><u><ol><ul><li><blockquote><pre><code><h1><h2><h3><h4><a><span>';
    $clean = strip_tags($html, $allowed);
    $clean = preg_replace('/\son\w+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $clean) ?? '';
    $clean = preg_replace('/\sstyle\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $clean) ?? '';
    $clean = preg_replace('/javascript\s*:/i', '', $clean) ?? '';

    return $clean;
}

function build_query(array $params): string
{
    return http_build_query(array_filter($params, function ($value) {
        return $value !== null && $value !== '';
    }));
}

function storage_path(string $path = ''): string
{
    $base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';

    if ($path === '') {
        return $base;
    }

    return $base . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
}

function ensure_directory(string $path): void
{
    if (is_dir($path)) {
        return;
    }

    mkdir($path, 0775, true);
}

function normalize_uploaded_files(array $files): array
{
    $normalized = [];
    $names = $files['name'] ?? [];
    $types = $files['type'] ?? [];
    $tmpNames = $files['tmp_name'] ?? [];
    $errors = $files['error'] ?? [];
    $sizes = $files['size'] ?? [];

    if (!is_array($names)) {
        return [[
            'name' => (string) $names,
            'type' => (string) ($types ?? ''),
            'tmp_name' => (string) ($tmpNames ?? ''),
            'error' => (int) ($errors ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($sizes ?? 0),
        ]];
    }

    foreach ($names as $index => $name) {
        $normalized[] = [
            'name' => (string) $name,
            'type' => (string) ($types[$index] ?? ''),
            'tmp_name' => (string) ($tmpNames[$index] ?? ''),
            'error' => (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE),
            'size' => (int) ($sizes[$index] ?? 0),
        ];
    }

    return $normalized;
}
