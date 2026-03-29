<?php

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function ensure_notes_share_slug_support(): void
{
    static $initialized = false;

    if ($initialized) {
        return;
    }

    $databaseName = (string) db()->query('SELECT DATABASE()')->fetchColumn();

    if ($databaseName === '') {
        $initialized = true;
        return;
    }

    $columnStmt = db()->prepare('
        SELECT COUNT(*)
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = :schema_name
          AND TABLE_NAME = :table_name
          AND COLUMN_NAME = :column_name
    ');
    $columnStmt->execute([
        'schema_name' => $databaseName,
        'table_name' => 'notes',
        'column_name' => 'share_slug',
    ]);

    if ((int) $columnStmt->fetchColumn() === 0) {
        db()->exec('ALTER TABLE notes ADD COLUMN share_slug VARCHAR(80) NULL AFTER share_token');
    }

    $indexStmt = db()->prepare('
        SELECT COUNT(*)
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = :schema_name
          AND TABLE_NAME = :table_name
          AND INDEX_NAME = :index_name
    ');
    $indexStmt->execute([
        'schema_name' => $databaseName,
        'table_name' => 'notes',
        'index_name' => 'uq_notes_share_slug',
    ]);

    if ((int) $indexStmt->fetchColumn() === 0) {
        db()->exec('ALTER TABLE notes ADD CONSTRAINT uq_notes_share_slug UNIQUE (share_slug)');
    }

    $indexStmt->execute([
        'schema_name' => $databaseName,
        'table_name' => 'notes',
        'index_name' => 'idx_notes_public_slug',
    ]);

    if ((int) $indexStmt->fetchColumn() === 0) {
        db()->exec('ALTER TABLE notes ADD INDEX idx_notes_public_slug (is_public, share_slug)');
    }

    $initialized = true;
}

function find_user_by_email_or_username(string $identifier): ?array
{
    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email_identifier OR username = :username_identifier LIMIT 1');
    $stmt->execute([
        'email_identifier' => $identifier,
        'username_identifier' => $identifier,
    ]);

    return $stmt->fetch() ?: null;
}

function create_user(string $username, string $email, string $password): void
{
    $stmt = db()->prepare('
        INSERT INTO users (username, email, password)
        VALUES (:username, :email, :password)
    ');

    $stmt->execute([
        'username' => $username,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
    ]);
}

function category_name_exists(int $userId, string $name, ?int $ignoreId = null): bool
{
    $sql = 'SELECT id FROM categories WHERE user_id = :user_id AND name = :name';
    $params = ['user_id' => $userId, 'name' => $name];

    if ($ignoreId !== null) {
        $sql .= ' AND id != :ignore_id';
        $params['ignore_id'] = $ignoreId;
    }

    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function get_categories(int $userId): array
{
    $stmt = db()->prepare('
        SELECT c.*, COUNT(n.id) AS note_count
        FROM categories c
        LEFT JOIN notes n ON n.category_id = c.id
        WHERE c.user_id = :user_id
        GROUP BY c.id
        ORDER BY c.name ASC
    ');
    $stmt->execute(['user_id' => $userId]);

    return $stmt->fetchAll();
}

function create_category(int $userId, string $name): int
{
    $stmt = db()->prepare('INSERT INTO categories (user_id, name) VALUES (:user_id, :name)');
    $stmt->execute(['user_id' => $userId, 'name' => $name]);

    return (int) db()->lastInsertId();
}

function get_category_for_user(int $userId, int $categoryId): ?array
{
    $stmt = db()->prepare('SELECT id, user_id, name FROM categories WHERE id = :id AND user_id = :user_id LIMIT 1');
    $stmt->execute([
        'id' => $categoryId,
        'user_id' => $userId,
    ]);

    return $stmt->fetch() ?: null;
}

function update_category(int $userId, int $categoryId, string $name): void
{
    $stmt = db()->prepare('UPDATE categories SET name = :name WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['name' => $name, 'id' => $categoryId, 'user_id' => $userId]);
}

function delete_category(int $userId, int $categoryId): void
{
    $stmt = db()->prepare('UPDATE notes SET category_id = NULL WHERE category_id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $categoryId, 'user_id' => $userId]);

    $stmt = db()->prepare('DELETE FROM categories WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $categoryId, 'user_id' => $userId]);
}

function get_dashboard_notes(int $userId, string $search, ?int $categoryId, int $page, int $perPage = 8): array
{
    ensure_notes_share_slug_support();

    $where = ['n.user_id = :user_id'];
    $params = ['user_id' => $userId];

    if ($search !== '') {
        $searchTerms = public_share_search_terms($search);
        $termSql = [];

        foreach ($searchTerms as $index => $term) {
            $titleKey = 'search_title_' . $index;
            $contentKey = 'search_content_' . $index;
            $slugKey = 'search_slug_' . $index;
            $tokenKey = 'search_token_' . $index;
            $prettyKey = 'search_pretty_' . $index;
            $tokenPathKey = 'search_token_path_' . $index;
            $slugPathKey = 'search_slug_path_' . $index;
            $likeTerm = '%' . $term . '%';

            $termSql[] = "(
                n.title LIKE :{$titleKey}
                OR n.content LIKE :{$contentKey}
                OR COALESCE(n.share_slug, '') LIKE :{$slugKey}
                OR n.share_token LIKE :{$tokenKey}
                OR CASE
                    WHEN n.share_slug IS NOT NULL AND n.share_slug != '' THEN CONCAT('/', n.share_slug)
                    ELSE ''
                END LIKE :{$prettyKey}
                OR CONCAT('/share.php?token=', n.share_token) LIKE :{$tokenPathKey}
                OR CASE
                    WHEN n.share_slug IS NOT NULL AND n.share_slug != '' THEN CONCAT('/share.php?slug=', n.share_slug)
                    ELSE ''
                END LIKE :{$slugPathKey}
            )";

            $params[$titleKey] = $likeTerm;
            $params[$contentKey] = $likeTerm;
            $params[$slugKey] = $likeTerm;
            $params[$tokenKey] = $likeTerm;
            $params[$prettyKey] = $likeTerm;
            $params[$tokenPathKey] = $likeTerm;
            $params[$slugPathKey] = $likeTerm;
        }

        if ($termSql !== []) {
            $where[] = '(' . implode(' OR ', $termSql) . ')';
        }
    }

    if ($categoryId !== null) {
        $where[] = 'n.category_id = :category_id';
        $params['category_id'] = $categoryId;
    }

    $whereSql = implode(' AND ', $where);

    $countStmt = db()->prepare("SELECT COUNT(*) FROM notes n WHERE {$whereSql}");
    foreach ($params as $key => $value) {
        $countStmt->bindValue(':' . $key, $value);
    }
    $countStmt->execute();
    $total = (int) $countStmt->fetchColumn();

    $offset = max(0, ($page - 1) * $perPage);

    $sql = "
        SELECT n.*, c.name AS category_name
        FROM notes n
        LEFT JOIN categories c ON c.id = n.category_id
        WHERE {$whereSql}
        ORDER BY n.updated_at DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = db()->prepare($sql);

    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }

    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return [
        'data' => $stmt->fetchAll(),
        'total' => $total,
        'pages' => max(1, (int) ceil($total / $perPage)),
        'page' => $page,
    ];
}

function create_note(int $userId, string $title, ?int $categoryId, string $content = '', int $isPublic = 0): int
{
    ensure_notes_share_slug_support();

    $stmt = db()->prepare('
        INSERT INTO notes (user_id, category_id, title, content, is_public, share_token, share_slug)
        VALUES (:user_id, :category_id, :title, :content, :is_public, :share_token, :share_slug)
    ');

    $stmt->execute([
        'user_id' => $userId,
        'category_id' => $categoryId,
        'title' => $title,
        'content' => clean_html($content),
        'is_public' => $isPublic,
        'share_token' => bin2hex(random_bytes(16)),
        'share_slug' => null,
    ]);

    return (int) db()->lastInsertId();
}

function ensure_note_attachments_table(): void
{
    db()->exec('
        CREATE TABLE IF NOT EXISTS note_attachments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            note_id INT UNSIGNED NOT NULL,
            user_id INT UNSIGNED NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL,
            file_size INT UNSIGNED NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_note_attachments_note
                FOREIGN KEY (note_id) REFERENCES notes(id)
                ON DELETE CASCADE,
            CONSTRAINT fk_note_attachments_user
                FOREIGN KEY (user_id) REFERENCES users(id)
                ON DELETE CASCADE,
            INDEX idx_note_attachments_note (note_id),
            INDEX idx_note_attachments_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
}

function get_note(int $userId, int $noteId): ?array
{
    ensure_notes_share_slug_support();

    $stmt = db()->prepare('
        SELECT n.*, c.name AS category_name
        FROM notes n
        LEFT JOIN categories c ON c.id = n.category_id
        WHERE n.id = :id AND n.user_id = :user_id
        LIMIT 1
    ');
    $stmt->execute(['id' => $noteId, 'user_id' => $userId]);

    return $stmt->fetch() ?: null;
}

function get_note_attachments(int $userId, int $noteId): array
{
    ensure_note_attachments_table();

    $stmt = db()->prepare('
        SELECT id, note_id, original_name, mime_type, file_size, created_at
        FROM note_attachments
        WHERE user_id = :user_id AND note_id = :note_id
        ORDER BY created_at DESC, id DESC
    ');
    $stmt->execute([
        'user_id' => $userId,
        'note_id' => $noteId,
    ]);

    return $stmt->fetchAll();
}

function get_attachment_for_user(int $userId, int $attachmentId): ?array
{
    ensure_note_attachments_table();

    $stmt = db()->prepare('
        SELECT a.*, n.title AS note_title
        FROM note_attachments a
        INNER JOIN notes n ON n.id = a.note_id
        WHERE a.id = :id AND a.user_id = :user_id
        LIMIT 1
    ');
    $stmt->execute([
        'id' => $attachmentId,
        'user_id' => $userId,
    ]);

    return $stmt->fetch() ?: null;
}

function create_note_attachment(
    int $userId,
    int $noteId,
    string $originalName,
    string $storedName,
    string $mimeType,
    int $fileSize
): int {
    ensure_note_attachments_table();

    $stmt = db()->prepare('
        INSERT INTO note_attachments (note_id, user_id, original_name, stored_name, mime_type, file_size)
        VALUES (:note_id, :user_id, :original_name, :stored_name, :mime_type, :file_size)
    ');
    $stmt->execute([
        'note_id' => $noteId,
        'user_id' => $userId,
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'mime_type' => $mimeType,
        'file_size' => $fileSize,
    ]);

    return (int) db()->lastInsertId();
}

function delete_note_attachment(int $userId, int $attachmentId): ?array
{
    ensure_note_attachments_table();

    $attachment = get_attachment_for_user($userId, $attachmentId);
    if (!$attachment) {
        return null;
    }

    $stmt = db()->prepare('DELETE FROM note_attachments WHERE id = :id AND user_id = :user_id');
    $stmt->execute([
        'id' => $attachmentId,
        'user_id' => $userId,
    ]);

    return $attachment;
}

function update_note(int $userId, int $noteId, string $title, ?int $categoryId, string $content, int $isPublic): void
{
    ensure_notes_share_slug_support();

    $stmt = db()->prepare('
        UPDATE notes
        SET title = :title,
            category_id = :category_id,
            content = :content,
            is_public = :is_public,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND user_id = :user_id
    ');

    $stmt->execute([
        'title' => $title,
        'category_id' => $categoryId,
        'content' => clean_html($content),
        'is_public' => $isPublic,
        'id' => $noteId,
        'user_id' => $userId,
    ]);
}

function share_slug_exists(string $slug, ?int $ignoreNoteId = null): bool
{
    ensure_notes_share_slug_support();

    $sql = 'SELECT id FROM notes WHERE share_slug = :share_slug';
    $params = ['share_slug' => $slug];

    if ($ignoreNoteId !== null && $ignoreNoteId > 0) {
        $sql .= ' AND id != :ignore_id';
        $params['ignore_id'] = $ignoreNoteId;
    }

    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function update_note_share_slug(int $userId, int $noteId, ?string $shareSlug): void
{
    ensure_notes_share_slug_support();

    $stmt = db()->prepare('
        UPDATE notes
        SET share_slug = :share_slug,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND user_id = :user_id
    ');
    $stmt->bindValue(':share_slug', $shareSlug, $shareSlug === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $stmt->bindValue(':id', $noteId, PDO::PARAM_INT);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
}

function delete_note(int $userId, int $noteId): void
{
    $stmt = db()->prepare('DELETE FROM notes WHERE id = :id AND user_id = :user_id');
    $stmt->execute(['id' => $noteId, 'user_id' => $userId]);
}

function regenerate_share_token(int $userId, int $noteId): string
{
    ensure_notes_share_slug_support();

    $token = bin2hex(random_bytes(16));

    $stmt = db()->prepare('
        UPDATE notes
        SET share_token = :share_token, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id AND user_id = :user_id
    ');
    $stmt->execute(['share_token' => $token, 'id' => $noteId, 'user_id' => $userId]);

    return $token;
}

function get_public_note(string $token): ?array
{
    ensure_notes_share_slug_support();

    $stmt = db()->prepare('
        SELECT n.*, u.username, c.name AS category_name
        FROM notes n
        INNER JOIN users u ON u.id = n.user_id
        LEFT JOIN categories c ON c.id = n.category_id
        WHERE n.share_token = :token AND n.is_public = 1
        LIMIT 1
    ');
    $stmt->execute(['token' => $token]);

    return $stmt->fetch() ?: null;
}

function get_public_note_by_slug(string $slug): ?array
{
    ensure_notes_share_slug_support();

    $stmt = db()->prepare('
        SELECT n.*, u.username, c.name AS category_name
        FROM notes n
        INNER JOIN users u ON u.id = n.user_id
        LEFT JOIN categories c ON c.id = n.category_id
        WHERE n.share_slug = :share_slug AND n.is_public = 1
        LIMIT 1
    ');
    $stmt->execute(['share_slug' => $slug]);

    return $stmt->fetch() ?: null;
}

function get_public_note_attachments(string $token): array
{
    ensure_note_attachments_table();
    ensure_notes_share_slug_support();

    $stmt = db()->prepare('
        SELECT a.id, a.note_id, a.original_name, a.mime_type, a.file_size, a.created_at
        FROM note_attachments a
        INNER JOIN notes n ON n.id = a.note_id
        WHERE n.share_token = :token AND n.is_public = 1
        ORDER BY a.created_at DESC, a.id DESC
    ');
    $stmt->execute(['token' => $token]);

    return $stmt->fetchAll();
}

function get_public_note_attachments_by_slug(string $slug): array
{
    ensure_note_attachments_table();
    ensure_notes_share_slug_support();

    $stmt = db()->prepare('
        SELECT a.id, a.note_id, a.original_name, a.mime_type, a.file_size, a.created_at
        FROM note_attachments a
        INNER JOIN notes n ON n.id = a.note_id
        WHERE n.share_slug = :share_slug AND n.is_public = 1
        ORDER BY a.created_at DESC, a.id DESC
    ');
    $stmt->execute(['share_slug' => $slug]);

    return $stmt->fetchAll();
}

function get_public_attachment(string $token, int $attachmentId): ?array
{
    ensure_note_attachments_table();
    ensure_notes_share_slug_support();

    $stmt = db()->prepare('
        SELECT a.*, n.title AS note_title
        FROM note_attachments a
        INNER JOIN notes n ON n.id = a.note_id
        WHERE a.id = :id AND n.share_token = :token AND n.is_public = 1
        LIMIT 1
    ');
    $stmt->execute([
        'id' => $attachmentId,
        'token' => $token,
    ]);

    return $stmt->fetch() ?: null;
}

function get_public_attachment_by_slug(string $slug, int $attachmentId): ?array
{
    ensure_note_attachments_table();
    ensure_notes_share_slug_support();

    $stmt = db()->prepare('
        SELECT a.*, n.title AS note_title
        FROM note_attachments a
        INNER JOIN notes n ON n.id = a.note_id
        WHERE a.id = :id AND n.share_slug = :share_slug AND n.is_public = 1
        LIMIT 1
    ');
    $stmt->execute([
        'id' => $attachmentId,
        'share_slug' => $slug,
    ]);

    return $stmt->fetch() ?: null;
}
