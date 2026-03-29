# Notes Taken

Notes Taken is a plain PHP 8 + MySQL note-taking app with authentication, categories, rich-text editing, file attachments, public sharing, autosave, and dashboard search.

## Features

- User accounts with registration, login, logout, and session-based authentication
- Optional invite-code gated registration through `APP_INVITE_CODE`
- Notes with create, edit, autosave, delete, and quick-create flow
- Rich-text editor powered by Quill
- Syntax-highlighted code blocks with copy button support on rendered notes
- Categories with create, rename, delete, and note counts
- Inline category creation while saving a note
- File attachments on notes
- Public/private note toggle per note
- Public note sharing with two link formats:
  - Default token URL: `/share.php?token=...`
  - Optional pretty slug URL: `/my-note-title`
- Public attachment downloads from shared notes
- Share link regeneration for the default token URL
- Dashboard search across:
  - note title
  - note content
  - custom share slug
  - share token
  - public share URL patterns like `/my-note-title`, `/share.php?token=...`, and `/share.php?slug=...`
- Dashboard display of public URLs for public notes
- Category filtering and pagination on the dashboard
- Light/dark theme toggle stored in local storage
- CSRF protection for form submissions
- Configurable absolute app base URL through `APP_URL`

## Requirements

- PHP 8.x recommended
- MySQL or MariaDB
- Apache is recommended if you want pretty share URLs via `.htaccess`

The app includes small PHP polyfills for older PHP functions, but PHP 8.x is the intended runtime.

## Run locally

1. Create a MySQL database.
2. Import `database/schema.sql`.
3. Create a `.env` file and set the values you need:
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
   - `APP_INVITE_CODE` (optional)
   - `APP_URL` (optional absolute base URL like `https://example.com` or `https://example.com/notes/public`)
4. Serve the project with `public` as the web root when possible.
5. Open `register.php` to create your first account.

Environment variables take precedence over `.env` values.

## Sharing

Public note sharing supports two URL styles.

1. Default token URL
   - Example: `/share.php?token=ae65e5cb12e438f71463606ac3d82328`
2. Pretty slug URL
   - Example: `/all-in-one`

If a custom slug is set, the note page shows both:
- Pretty URL
- Default token URL

If no custom slug is set, the app keeps using the default token URL.

### Pretty URL support

Pretty URLs like `/all-in-one` require Apache rewrite support with `public/.htaccess` enabled.

If rewrite is not available, shared notes still work through:
- `/share.php?token=...`
- `/share.php?slug=all-in-one`

### Share URL examples

If your local base URL is `http://localhost:8000/public`:
- Default token URL: `http://localhost:8000/public/share.php?token=ae65e5cb12e438f71463606ac3d82328`
- Pretty slug URL: `http://localhost:8000/public/all-in-one`
- Slug fallback without rewrite: `http://localhost:8000/public/share.php?slug=all-in-one`

## Notes and Editor

- New notes can be created from the dashboard quick-entry form
- Full notes can be edited on the note page
- Autosave updates existing notes in the background
- Notes can be marked public or private
- Public notes can optionally use a custom slug
- The note page shows current public share URLs
- Default share tokens can be regenerated

## Attachments

Attachments are stored under `storage/attachments` and linked to notes.

Supported upload categories include:
- PDF and common office documents
- Plain text, Markdown, CSV, JSON
- ZIP, RAR, 7z, gzip
- Common images
- MP3, MP4, and some other media types

Attachment limit:
- Maximum size per file: `10 MB`

Attachments can be:
- downloaded privately by the note owner
- downloaded publicly when the note is public
- removed from a note

## Categories

Categories support:
- create
- rename
- delete
- note counts
- filtering notes on the dashboard

Deleting a category does not delete its notes. Notes become uncategorized instead.

## Search and Dashboard

The dashboard supports:
- keyword search
- category filters
- pagination
- public URL display for public notes

Search can match plain text like:
- note title
- note body content
- custom slug
- share token

Search can also match pasted or partial public URLs like:
- `all-in-one`
- `/all-in-one`
- `share.php?token=...`
- `share.php?slug=all-in-one`
- `http://localhost:8000/public/all-in-one`

## Security and App Behavior

- Session-based auth
- CSRF tokens on forms
- HTML cleaning for stored rich-text content
- Public note access only when `is_public = 1`
- Share slugs are normalized to lowercase letters, numbers, and hyphens
- Share slugs are unique across notes

## Configuration

Environment settings used by the app:

- `DB_HOST`: database host
- `DB_PORT`: database port
- `DB_NAME`: database name
- `DB_USER`: database user
- `DB_PASS`: database password
- `APP_INVITE_CODE`: optional registration invite code
- `APP_URL`: optional absolute base URL for generated links

Use `APP_URL` if generated public links point to the wrong domain, scheme, port, or subdirectory.

## Project Structure

- `config/` bootstrap, environment loading, session setup, PDO connection
- `includes/` helpers, repositories, shared layout partials
- `auth/` authentication services
- `notes/` note actions and attachment handling
- `categories/` category actions
- `database/` SQL schema
- `public/` web entry points, dashboard, note editor, auth pages, share pages, assets, rewrite rules
- `storage/` uploaded attachment files
- `assets/` source assets used by the app

## Main Pages

- `public/index.php`: landing redirect
- `public/register.php`: account registration
- `public/login.php`: sign in
- `public/logout.php`: sign out
- `public/dashboard.php`: note list, search, filters, quick create
- `public/note.php`: note editor, sharing controls, attachments
- `public/categories.php`: category management
- `public/share.php`: public shared note page
- `public/share_attachment.php`: public attachment download
- `public/attachment.php`: private attachment download

## Deployment Notes

- Apache-based shared hosting is the easiest target if you want pretty share URLs
- On hosts like SiteGround, Apache-style `.htaccess` rules are generally relevant
- If rewrite is unavailable, keep using token URLs or `share.php?slug=...`
- If your app is installed in a subdirectory, set `APP_URL` so generated public links are correct
