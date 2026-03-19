<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/repositories.php';

require_auth();

$userId = current_user_id();
$noteId = (int) ($_GET['id'] ?? 0);
$note = $noteId > 0 ? get_note($userId, $noteId) : null;
$attachments = $note ? get_note_attachments($userId, (int) $note['id']) : [];
$categories = get_categories($userId);
$pageTitle = $note ? 'Edit Note' : 'Create Note';

if ($noteId > 0 && !$note) {
    http_response_code(404);
    exit('Note not found.');
}

$shareUrl = '';
if ($note && (int) $note['is_public'] === 1) {
    $shareUrl = absolute_app_url('share.php?token=' . $note['share_token']);
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="hero-panel compact mb-4">
    <div>
        <p class="eyebrow mb-2"><?= $note ? 'Editing note' : 'New draft' ?></p>
        <h1 class="hero-title mb-2"><?= e($note['title'] ?? 'Untitled note') ?></h1>
        <p class="hero-copy mb-0">A cleaner editor with separated writing, metadata, and sharing controls.</p>
    </div>
    <div>
        <a class="btn app-btn app-btn-ghost" href="<?= e(app_url('dashboard.php')) ?>">Back to dashboard</a>
    </div>
</section>

<div class="row g-4 align-items-start">
    <div class="col-xl-8">
        <section class="editor-card">
            <form method="post" action="<?= e(app_url('note_actions.php')) ?>" enctype="multipart/form-data">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="note_id" value="<?= (int) ($note['id'] ?? 0) ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-7 field-stack">
                        <label class="field-label">Title</label>
                        <input class="form-control form-control-lg" name="title" required value="<?= e($note['title'] ?? '') ?>">
                    </div>
                    <div class="col-md-5 field-stack">
                        <label class="field-label">Category</label>
                        <select class="form-select form-select-lg" name="category_id">
                            <option value="">Uncategorized</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int) $category['id'] ?>" <?= (int) ($note['category_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field-stack mb-3">
                    <label class="field-label">Create a new category inline</label>
                    <input class="form-control" name="new_category" placeholder="Optional new category">
                </div>

                <div class="field-stack mb-3">
                    <label class="field-label">Content</label>
                    <div class="editor-shell">
                        <div data-note-editor data-note-id="<?= (int) ($note['id'] ?? 0) ?>" data-autosave-url="<?= e(app_url('note_actions.php')) ?>" data-csrf="<?= e(csrf_token()) ?>"></div>
                    </div>
                    <textarea class="d-none" name="content" data-note-content><?= e($note['content'] ?? '') ?></textarea>
                </div>

                <div class="field-stack mb-3">
                    <label class="field-label" for="attachments">Attachments</label>
                    <input class="form-control" id="attachments" type="file" name="attachments[]" multiple>
                    <p class="upload-hint mb-0">Upload documents, images, archives, audio, or video up to <?= e(format_bytes(NOTE_ATTACHMENT_MAX_BYTES)) ?> each.</p>
                </div>

                <div class="editor-footer">
                    <div class="form-check form-switch public-switch">
                        <input class="form-check-input" type="checkbox" role="switch" id="publicSwitch" name="is_public" <?= isset($note['is_public']) && (int) $note['is_public'] === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="publicSwitch">Enable public sharing</label>
                    </div>
                    <div class="editor-actions">
                        <span class="small text-secondary" data-autosave-status><?= $note ? 'Autosave ready' : 'Autosave starts after the first save' ?></span>
                        <button class="btn app-btn app-btn-primary" type="submit">Save note</button>
                    </div>
                </div>
            </form>
        </section>
    </div>

    <div class="col-xl-4">
        <section class="share-card mb-4">
            <p class="eyebrow mb-1">Share</p>
            <h2 class="section-title mb-2">Public link</h2>
            <?php if ($shareUrl !== ''): ?>
                <p class="code-chip mb-3"><?= e($shareUrl) ?></p>
                <form method="post" action="<?= e(app_url('note_actions.php')) ?>" class="d-grid">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="regenerate_share">
                    <input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>">
                    <button class="btn app-btn app-btn-ghost" type="submit">Refresh link</button>
                </form>
            <?php else: ?>
                <p class="text-secondary mb-0">Turn on sharing and save the note to generate a public URL.</p>
            <?php endif; ?>
        </section>

        <?php if ($note): ?>
            <section class="share-card mb-4">
                <p class="eyebrow mb-1">Metadata</p>
                <div class="meta-list">
                    <div><span>Category</span><strong><?= e($note['category_name'] ?: 'Uncategorized') ?></strong></div>
                    <div><span>Created</span><strong><?= e(format_datetime($note['created_at'])) ?></strong></div>
                    <div><span>Updated</span><strong><?= e(format_datetime($note['updated_at'])) ?></strong></div>
                    <div><span>Attachments</span><strong><?= count($attachments) ?></strong></div>
                </div>
            </section>

            <section class="share-card mb-4">
                <p class="eyebrow mb-1">Attachments</p>
                <h2 class="section-title mb-2">Files on this note</h2>
                <?php if ($attachments): ?>
                    <div class="attachment-list">
                        <?php foreach ($attachments as $attachment): ?>
                            <article class="attachment-item">
                                <div class="attachment-copy">
                                    <strong><?= e($attachment['original_name']) ?></strong>
                                    <span><?= e(strtoupper($attachment['mime_type'])) ?> · <?= e(format_bytes((int) $attachment['file_size'])) ?></span>
                                </div>
                                <div class="attachment-actions">
                                    <a class="btn app-btn app-btn-ghost attachment-btn" href="<?= e(app_url('attachment.php?id=' . (int) $attachment['id'])) ?>">Download</a>
                                    <form method="post" action="<?= e(app_url('note_actions.php')) ?>" onsubmit="return confirm('Delete this attachment?');">
                                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="delete_attachment">
                                        <input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>">
                                        <input type="hidden" name="attachment_id" value="<?= (int) $attachment['id'] ?>">
                                        <button class="btn btn-outline-danger attachment-btn" type="submit">Remove</button>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-secondary mb-0">Files you upload here stay attached to this note and are available after the first save.</p>
                <?php endif; ?>
            </section>

            <section class="share-card danger-card">
                <p class="eyebrow mb-1">Danger zone</p>
                <p class="text-secondary small">Delete the note permanently. This cannot be undone.</p>
                <form method="post" action="<?= e(app_url('note_actions.php')) ?>" onsubmit="return confirm('Delete this note?');">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="note_id" value="<?= (int) $note['id'] ?>">
                    <button class="btn btn-outline-danger w-100" type="submit">Delete note</button>
                </form>
            </section>
        <?php endif; ?>
    </div>
</div>

<?php if ($note): ?>
<section class="app-card mt-4 note-preview-card">
    <div class="panel-header mb-3">
        <div>
            <p class="eyebrow mb-1">Rendered view</p>
            <h2 class="section-title mb-0">Preview</h2>
        </div>
    </div>
    <article class="note-content"><?= $note['content'] ?></article>
</section>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
