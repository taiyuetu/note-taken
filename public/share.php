<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/repositories.php';

$token = trim($_GET['token'] ?? '');
$slug = normalize_share_slug($_GET['slug'] ?? null);
$note = null;
$attachments = [];
$attachmentIdentifier = [];

if ($slug !== null) {
    $note = get_public_note_by_slug($slug);
    $attachments = $note ? get_public_note_attachments_by_slug($slug) : [];
    $attachmentIdentifier = ['slug' => $slug];
} elseif ($token !== '') {
    $note = get_public_note($token);
    $attachments = $note ? get_public_note_attachments($token) : [];
    $attachmentIdentifier = ['token' => $token];
}

$pageTitle = 'Shared Note';

require_once __DIR__ . '/../includes/header.php';
?>
<section class="share-card shared-stage mx-auto" style="max-width: 920px;">
    <?php if (!$note): ?>
        <p class="eyebrow mb-2">Unavailable</p>
        <h1 class="hero-title mb-2">Shared note not found</h1>
        <p class="hero-copy mb-0">The note may be private, removed, or the link is invalid.</p>
    <?php else: ?>
        <p class="eyebrow mb-2">Shared note</p>
        <h1 class="hero-title mb-3"><?= e($note['title']) ?></h1>
        <p class="hero-copy mb-4">By <?= e($note['username']) ?> | <?= e($note['category_name'] ?: 'Uncategorized') ?> | Updated <?= e(format_datetime($note['updated_at'])) ?></p>
        <article class="note-content"><?= $note['content'] ?></article>
        <?php if ($attachments): ?>
            <section class="share-card mt-4">
                <p class="eyebrow mb-1">Attachments</p>
                <h2 class="section-title mb-2">Files</h2>
                <div class="attachment-list">
                    <?php foreach ($attachments as $attachment): ?>
                        <article class="attachment-item">
                            <div class="attachment-copy">
                                <strong><?= e($attachment['original_name']) ?></strong>
                                <span><?= e(strtoupper($attachment['mime_type'])) ?> | <?= e(format_bytes((int) $attachment['file_size'])) ?></span>
                            </div>
                            <div class="attachment-actions">
                                <a class="btn app-btn app-btn-ghost attachment-btn" href="<?= e(app_url('share_attachment.php?' . build_query($attachmentIdentifier + ['id' => (int) $attachment['id']]))) ?>">Download</a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
