<?php

declare(strict_types=1);

require_once __DIR__ . '/../auth/services.php';

require_guest();

$pageTitle = 'Register';

if (is_post()) {
    verify_csrf();
    store_old_input($_POST);
    [$errors, $username, $email, $password] = validate_registration($_POST);

    if ($errors === []) {
        create_user($username, $email, $password);
        clear_old_input();
        flash('success', 'Account created. Please sign in.');
        redirect('login.php');
    }

    foreach ($errors as $error) {
        flash('danger', $error);
    }
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="auth-shell">
    <section class="auth-stage">
        <div class="auth-aside">
            <p class="eyebrow mb-2">New workspace</p>
            <h1 class="hero-title mb-3">Build a note library that stays readable as it grows.</h1>
            <p class="hero-copy mb-4">Start with a private account, then organize notes into categories, draft long-form content, and publish selected notes through public links.</p>
            <div class="auth-metric-grid">
                <div class="metric-card"><strong>Categories</strong><span>Organize by project, topic, or client.</span></div>
                <div class="metric-card"><strong>Autosave</strong><span>Drafts update without interrupting writing.</span></div>
                <div class="metric-card"><strong>Share links</strong><span>Publish only what you explicitly expose.</span></div>
            </div>
        </div>
        <div class="app-card auth-card">
            <p class="eyebrow mb-2">Create account</p>
            <h2 class="section-title mb-2">Register</h2>
            <p class="text-secondary mb-4">Set up your private workspace.<?= registration_requires_invite_code() ? ' An invite code is required.' : '' ?></p>
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                <?php if (registration_requires_invite_code()): ?>
                <div class="field-stack">
                    <label class="field-label">Invite code</label>
                    <input class="form-control" name="invite_code" required value="<?= e(old('invite_code')) ?>">
                </div>
                <?php endif; ?>
                <div class="field-stack">
                    <label class="field-label">Username</label>
                    <input class="form-control" name="username" required minlength="3" value="<?= e(old('username')) ?>">
                </div>
                <div class="field-stack">
                    <label class="field-label">Email</label>
                    <input class="form-control" type="email" name="email" required value="<?= e(old('email')) ?>">
                </div>
                <div class="field-stack">
                    <label class="field-label">Password</label>
                    <input class="form-control" type="password" name="password" required minlength="8">
                </div>
                <div class="field-stack">
                    <label class="field-label">Confirm password</label>
                    <input class="form-control" type="password" name="confirm_password" required minlength="8">
                </div>
                <button class="btn app-btn app-btn-primary" type="submit">Register</button>
                <p class="small text-secondary mb-0">Already registered? <a href="<?= e(app_url('login.php')) ?>">Login</a></p>
            </form>
        </div>
    </section>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>