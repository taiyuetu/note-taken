<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/repositories.php';

function registration_requires_invite_code(): bool
{
    return app_invite_code() !== '';
}

function validate_registration(array $input): array
{
    $errors = [];
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $confirm = $input['confirm_password'] ?? '';
    $inviteCode = trim($input['invite_code'] ?? '');

    if (registration_requires_invite_code()) {
        if ($inviteCode === '') {
            $errors[] = 'Invite code is required.';
        } elseif (!hash_equals(app_invite_code(), $inviteCode)) {
            $errors[] = 'Invite code is invalid.';
        }
    }

    if ($username === '' || strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (find_user_by_email_or_username($username) || find_user_by_email_or_username($email)) {
        $errors[] = 'That username or email is already in use.';
    }

    return [$errors, $username, $email, $password];
}

function attempt_login(string $identifier, string $password): bool
{
    $user = find_user_by_email_or_username(trim($identifier));

    if (!$user || !password_verify($password, $user['password'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];

    return true;
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
