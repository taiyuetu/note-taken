# Notes Taken

Plain PHP 8 and MySQL note-taking application with authentication, categories, rich-text notes, sharing, autosave, and optional invite-code gated registration.

## Run locally

1. Create a MySQL database and import `database/schema.sql`.
2. Set database environment variables if you do not want the defaults:
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASS`
   - `APP_INVITE_CODE` (set this to require an invite code for registration)
3. Serve the project through Apache or PHP's built-in server with `public` as the document root when possible.
4. If you want to restrict signups, set `APP_INVITE_CODE` before opening `register.php`.
5. Open `register.php` to create your first account.

## Structure

- `config/` app bootstrap and PDO connection
- `includes/` helpers, repository functions, layout partials
- `auth/` authentication routes
- `notes/` note CRUD and editor flow
- `categories/` category management
- `public/` dashboard, landing redirect, public sharing page
- `assets/` CSS and JavaScript