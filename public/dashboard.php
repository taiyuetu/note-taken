<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/repositories.php';

require_auth();

$userId = current_user_id();
$search = trim($_GET['search'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0) ?: null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$categories = get_categories($userId);
$notesPage = get_dashboard_notes($userId, $search, $categoryId, $page);
$pageTitle = 'Dashboard';
$activeCategory = null;

foreach ($categories as $category) {
    if ($categoryId === (int) $category['id']) {
        $activeCategory = $category;
        break;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<section class="hero-panel mb-4 mb-lg-5">
    <div>
        <p class="eyebrow mb-2">Workspace</p>
        <h1 class="hero-title mb-3"><?= e($activeCategory['name'] ?? 'All notes') ?></h1>
        <p class="hero-copy mb-0">A warmer, calmer writing surface with faster retrieval. Search everything, filter by category, and jump straight into editing.</p>
    </div>
    <div class="hero-stats">
        <div class="stat-block">
            <span class="stat-number"><?= (int) $notesPage['total'] ?></span>
            <span class="stat-label">Matched notes</span>
        </div>
        <div class="stat-block">
            <span class="stat-number"><?= count($categories) ?></span>
            <span class="stat-label">Categories</span>
        </div>
        <div class="stat-block">
            <span class="stat-number"><?= (int) $notesPage['page'] ?></span>
            <span class="stat-label">Current page</span>
        </div>
    </div>
</section>

<div class="dashboard-grid">
    <aside class="sidebar-card workspace-sidebar">
        <div class="sidebar-header">
            <div>
                <p class="eyebrow mb-1">Collections</p>
                <h2 class="section-title mb-0">Browse by category</h2>
            </div>
            <a class="btn app-btn app-btn-ghost btn-sm" href="<?= e(app_url('categories.php')) ?>">Manage</a>
        </div>

        <div class="category-stack">
            <a class="category-link <?= $categoryId === null ? 'is-active' : '' ?>" href="<?= e(app_url('dashboard.php')) ?>">
                <span>All notes</span>
                <span class="category-count"><?= (int) $notesPage['total'] ?></span>
            </a>
            <?php foreach ($categories as $category): ?>
                <a class="category-link <?= $categoryId === (int) $category['id'] ? 'is-active' : '' ?>" href="<?= e(app_url('dashboard.php?' . build_query(['category' => $category['id'], 'search' => $search]))) ?>">
                    <span><?= e($category['name']) ?></span>
                    <span class="category-count"><?= (int) $category['note_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </aside>

    <section class="app-card content-panel">
        <div class="quick-entry mb-4">
            <div>
                <p class="eyebrow mb-1">Fast capture</p>
                <h2 class="section-title mb-1">Drop in a title and keep moving</h2>
                <p class="text-secondary mb-0">A blank note is enough to start. Structure can follow later.</p>
            </div>
            <form method="post" action="<?= e(app_url('note_actions.php')) ?>" class="quick-entry-form">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="quick_create">
                <input class="form-control" name="title" placeholder="Tonight's reading notes">
                <button class="btn app-btn app-btn-primary" type="submit">Create</button>
            </form>
        </div>

        <form class="search-panel mb-4" method="get">
            <div class="field-stack">
                <label class="field-label">Search</label>
                <input class="form-control" name="search" placeholder="Search title, content, slug, or public URL" value="<?= e($search) ?>">
            </div>
            <div class="field-stack">
                <label class="field-label">Category</label>
                <select class="form-select" name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= $categoryId === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-grid">
                <label class="field-label d-none d-lg-block">Run</label>
                <button class="btn app-btn app-btn-ghost" type="submit">Apply filters</button>
            </div>
        </form>

        <div class="panel-header mb-3">
            <div>
                <p class="eyebrow mb-1">Library</p>
                <h2 class="section-title mb-0">Your notes</h2>
            </div>
            <a class="btn app-btn app-btn-primary" href="<?= e(app_url('note.php')) ?>">New note</a>
        </div>

        <div class="note-list">
            <?php if ($notesPage['data'] === []): ?>
                <div class="note-card empty-state">
                    <p class="eyebrow mb-2">No results</p>
                    <h3 class="h4 mb-2">Nothing matches this view yet</h3>
                    <p class="text-secondary mb-0">Clear the filters or create a new note from the quick entry bar.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($notesPage['data'] as $note): ?>
                <?php
                $prettyUrl = '';
                $tokenUrl = '';
                if ((int) $note['is_public'] === 1) {
                    $prettyUrl = !empty($note['share_slug']) ? public_share_url($note) : '';
                    $tokenUrl = absolute_app_url('share.php?token=' . rawurlencode((string) $note['share_token']));
                }
                ?>
                <article class="note-card">
                    <div class="note-card-top">
                        <div>
                            <p class="eyebrow mb-2"><?= e($note['category_name'] ?: 'Uncategorized') ?></p>
                            <h3 class="note-title mb-2"><a class="text-decoration-none text-reset" href="<?= e(app_url('note.php?id=' . $note['id'])) ?>"><?= e($note['title']) ?></a></h3>
                        </div>
                        <div class="note-card-meta">
                            <?php if ((int) $note['is_public'] === 1): ?>
                                <span class="pill-tag">Public</span>
                            <?php endif; ?>
                            <span class="meta-chip">Updated <?= e(format_datetime($note['updated_at'])) ?></span>
                        </div>
                    </div>
                    <p class="note-excerpt mb-3"><?= e(excerpt($note['content'])) ?></p>
                    <?php if ($tokenUrl !== ''): ?>
                        <div class="dashboard-share-links mb-3">
                            <?php if ($prettyUrl !== ''): ?>
                                <div>
                                    <span class="dashboard-share-label">Pretty URL</span>
                                    <span class="code-chip dashboard-share-chip"><?= e($prettyUrl) ?></span>
                                </div>
                            <?php endif; ?>
                            <div>
                                <span class="dashboard-share-label">Token URL</span>
                                <span class="code-chip dashboard-share-chip"><?= e($tokenUrl) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="note-card-foot">
                        <span class="mono small text-secondary">ID <?= (int) $note['id'] ?></span>
                        <a class="note-link" href="<?= e(app_url('note.php?id=' . $note['id'])) ?>">Open note</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($notesPage['pages'] > 1): ?>
            <nav class="mt-4">
                <ul class="pagination app-pagination mb-0">
                    <?php for ($i = 1; $i <= $notesPage['pages']; $i++): ?>
                        <li class="page-item <?= $i === $notesPage['page'] ? 'active' : '' ?>">
                            <a class="page-link" href="<?= e(app_url('dashboard.php?' . build_query(['search' => $search, 'category' => $categoryId, 'page' => $i]))) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
