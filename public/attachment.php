<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/repositories.php';

require_auth();

$userId = current_user_id();
$attachmentId = (int) ($_GET['id'] ?? 0);
$attachment = $attachmentId > 0 ? get_attachment_for_user((int) $userId, $attachmentId) : null;

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
