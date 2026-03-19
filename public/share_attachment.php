<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/repositories.php';

$token = trim($_GET['token'] ?? '');
$attachmentId = (int) ($_GET['id'] ?? 0);
$attachment = ($token !== '' && $attachmentId > 0) ? get_public_attachment($token, $attachmentId) : null;

if (!$attachment) {
    http_response_code(404);
    exit('Attachment not found.');
}

$path = storage_path('attachments/' . $attachment['stored_name']);

if (!is_file($path)) {
    http_response_code(404);
    exit('Attachment file is missing.');
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $attachment['mime_type']);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="' . rawurlencode($attachment['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;