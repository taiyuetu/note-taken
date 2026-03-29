CREATE DATABASE IF NOT EXISTS notes_taken CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE notes_taken;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_categories_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT uq_categories_user_name UNIQUE (user_id, name),
    INDEX idx_categories_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    title VARCHAR(180) NOT NULL,
    content MEDIUMTEXT NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 0,
    share_token CHAR(32) NOT NULL,
    share_slug VARCHAR(80) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notes_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_notes_category
        FOREIGN KEY (category_id) REFERENCES categories(id)
        ON DELETE SET NULL,
    CONSTRAINT uq_notes_share_token UNIQUE (share_token),
    CONSTRAINT uq_notes_share_slug UNIQUE (share_slug),
    INDEX idx_notes_user_updated (user_id, updated_at),
    INDEX idx_notes_user_category (user_id, category_id),
    INDEX idx_notes_public_token (is_public, share_token),
    INDEX idx_notes_public_slug (is_public, share_slug)
) ENGINE=InnoDB;

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
) ENGINE=InnoDB;
