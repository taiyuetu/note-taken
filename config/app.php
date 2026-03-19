<?php

declare(strict_types=1);

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