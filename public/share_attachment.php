<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/repositories.php';

$token = trim($_GET['token'] ?? '');
$slug = normalize_share_slug($_GET['slug'] ?? null);
$attachmentId = (int) ($_GET['id'] ?? 0);
$attachment = null;

if ($slug !== null && $attachmentId > 0) {
    $attachment = get_public_attachment_by_slug($slug, $attachmentId);
} elseif ($token !== '' && $attachmentId > 0) {
    $attachment = get_public_attachment($token, $attachmentId);
}

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
