<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/services.php';

if (!is_post()) {
    redirect('login.php');
}

verify_csrf();

if (current_user_id() !== null) {
    logout_user();
}

flash('success', 'You have been logged out.');
redirect('login.php');
