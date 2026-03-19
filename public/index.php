<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/services.php';

if (current_user_id() !== null) {
    redirect('dashboard.php');
}

redirect('login.php');

