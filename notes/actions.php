<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/repositories.php';

require_auth();

function allowed_attachment_mime_types(): array
{
    return [
        'application/gzip',
        'application/json',
        'application/msword',
        'application/pdf',
        'application/rtf',
        'application/vnd.ms-excel',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/x-7z-compressed',
        'application/x-rar-compressed',
        'application/zip',
        'audio/mpeg',
        'audio/mp4',
        'image/gif',
        'image/jpeg',
        'image/png',
        'image/svg+xml',
        'image/webp',
        'text/csv',
        'text/markdown',
        'text/plain',
        'video/mp4',
    ];
}

function upload_error_message(int $errorCode): string
{
    return match ($errorCode) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'One of the selected files exceeds the upload size limit.',
        UPLOAD_ERR_PARTIAL => 'One of the selected files did not finish uploading.',
        UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload storage is not available on the server.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write one of the uploaded files.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension blocked one of the uploaded files.',
        default => 'One of the selected files could not be uploaded.',
    };
}

function save_uploaded_attachments(int $userId, int $noteId, array $files): array
{
    $savedCount = 0;
    $errors = [];
    $uploadDirectory = storage_path('attachments');
    ensure_directory($uploadDirectory);
    $allowedMimeTypes = allowed_attachment_mime_types();
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    foreach (normalize_uploaded_files($files) as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || trim((string) ($file['name'] ?? '')) === '') {
            continue;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors[] = upload_error_message((int) $file['error']);
            continue;
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            $errors[] = 'The uploaded file payload was invalid.';
            continue;
        }

        if ((int) $file['size'] <= 0) {
            $errors[] = 'Empty files are not allowed.';
            continue;
        }

        if ((int) $file['size'] > NOTE_ATTACHMENT_MAX_BYTES) {
            $errors[] = 'Files must be 10 MB or smaller.';
            continue;
        }

        $mimeType = $finfo ? (string) finfo_file($finfo, $file['tmp_name']) : '';
        if ($mimeType === '' || !in_array($mimeType, $allowedMimeTypes, true)) {
            $errors[] = 'Unsupported file type for "' . trim((string) $file['name']) . '".';
            continue;
        }

        $originalName = trim((string) $file['name']);
        $safeOriginalName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $originalName) ?: 'attachment';
        $storedName = bin2hex(random_bytes(16)) . '-' . $safeOriginalName;
        $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            $errors[] = 'The server could not store "' . $originalName . '".';
            continue;
        }

        create_note_attachment($userId, $noteId, $originalName, $storedName, $mimeType, (int) $file['size']);
        $savedCount++;
    }

    if ($finfo) {
        finfo_close($finfo);
    }

    return ['saved' => $savedCount, 'errors' => $errors];
}

function remove_attachment_file(array $attachment): void
{
    $path = storage_path('attachments/' . $attachment['stored_name']);

    if (is_file($path)) {
        unlink($path);
    }
}

$userId = current_user_id();
$action = $_POST['action'] ?? '';
$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

if (!is_post()) {
    redirect('dashboard.php');
}

verify_csrf();

if ($userId === null) {
    redirect('login.php');
}

function normalize_category_id_for_user(int $userId, ?int $categoryId): ?int
{
    if ($categoryId === null || $categoryId <= 0) {
        return null;
    }

    return get_category_for_user($userId, $categoryId) ? $categoryId : null;
}

if ($action === 'quick_create') {
    $title = trim($_POST['title'] ?? 'Untitled note');
    $noteId = create_note($userId, $title !== '' ? $title : 'Untitled note', null, '');
    flash('success', 'Note created.');
    redirect('note.php?id=' . $noteId);
}

if ($action === 'save') {
    $noteId = (int) ($_POST['note_id'] ?? 0);
    $title = trim($_POST['title'] ?? 'Untitled note');
    $content = trim($_POST['content'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $shareSlug = normalize_share_slug($_POST['share_slug'] ?? null);
    $newCategory = trim($_POST['new_category'] ?? '');

    if ($shareSlug !== null && share_slug_exists($shareSlug, $noteId > 0 ? $noteId : null)) {
        flash('warning', 'That public URL is already in use. Please choose another one.');
        redirect('note.php' . ($noteId > 0 ? '?id=' . $noteId : ''));
    }

    if ($newCategory !== '') {
        if (!category_name_exists($userId, $newCategory)) {
            $categoryId = create_category($userId, $newCategory);
        } else {
            $existing = array_values(array_filter(get_categories($userId), static fn ($category) => $category['name'] === $newCategory));
            $categoryId = isset($existing[0]['id']) ? (int) $existing[0]['id'] : 0;
        }
    }

    $categoryId = normalize_category_id_for_user($userId, $categoryId > 0 ? $categoryId : null);

    if ($noteId > 0) {
        update_note($userId, $noteId, $title !== '' ? $title : 'Untitled note', $categoryId, $content, $isPublic);
        $message = 'Note updated.';
    } else {
        $noteId = create_note($userId, $title !== '' ? $title : 'Untitled note', $categoryId, $content, $isPublic);
        $message = 'Note created.';
    }

    update_note_share_slug($userId, $noteId, $shareSlug);

    if (isset($_FILES['attachments'])) {
        $uploadResult = save_uploaded_attachments($userId, $noteId, $_FILES['attachments']);
        if ($uploadResult['saved'] > 0) {
            $message .= ' ' . $uploadResult['saved'] . ' attachment' . ($uploadResult['saved'] === 1 ? '' : 's') . ' uploaded.';
        }

        foreach ($uploadResult['errors'] as $errorMessage) {
            flash('warning', $errorMessage);
        }
    }

    flash('success', $message);
    redirect('note.php?id=' . $noteId);
}

if ($action === 'autosave') {
    $noteId = (int) ($_POST['note_id'] ?? 0);

    if ($noteId > 0) {
        update_note(
            $userId,
            $noteId,
            trim($_POST['title'] ?? 'Untitled note') ?: 'Untitled note',
            normalize_category_id_for_user($userId, (int) ($_POST['category_id'] ?? 0) ?: null),
            trim($_POST['content'] ?? ''),
            (int) ($_POST['is_public'] ?? 0)
        );

        $shareSlug = normalize_share_slug($_POST['share_slug'] ?? null);
        if ($shareSlug === null || !share_slug_exists($shareSlug, $noteId)) {
            update_note_share_slug($userId, $noteId, $shareSlug);
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'saved_at' => date('H:i:s')]);
    exit;
}

if ($action === 'delete') {
    $noteId = (int) ($_POST['note_id'] ?? 0);

    foreach (get_note_attachments($userId, $noteId) as $attachment) {
        remove_attachment_file($attachment);
    }

    delete_note($userId, $noteId);
    flash('success', 'Note deleted.');
    redirect('dashboard.php');
}

if ($action === 'delete_attachment') {
    $noteId = (int) ($_POST['note_id'] ?? 0);
    $attachmentId = (int) ($_POST['attachment_id'] ?? 0);
    $attachment = delete_note_attachment($userId, $attachmentId);

    if ($attachment) {
        remove_attachment_file($attachment);
        flash('success', 'Attachment removed.');
    } else {
        flash('warning', 'Attachment not found.');
    }

    redirect('note.php?id=' . $noteId);
}

if ($action === 'regenerate_share') {
    $noteId = (int) ($_POST['note_id'] ?? 0);
    regenerate_share_token($userId, $noteId);
    flash('success', 'Default share link refreshed.');
    redirect('note.php?id=' . $noteId);
}

redirect('dashboard.php');
