<?php

require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';

start_secure_session();

if (is_logged_in()) {
    redirect('dashboard.php');
}

$page_title = 'Sign In';
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (is_locked_out()) {
        $errors[] = 'Too many failed login attempts. Please wait 15 minutes and try again.';
    } else {
        $username = clean_string($_POST['username'] ?? '', 50);
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $errors[] = 'Please enter your username and password.';
        } else {
            try {
                $db   = get_db();
                $stmt = $db->prepare(
                    'SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1'
                );
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    clear_login_attempts();
                    session_regenerate_id(true);

                    $_SESSION['user_id']      = (int)$user['id'];
                    $_SESSION['username']     = $user['username'];
                    $_SESSION['user_role']    = $user['role'];
                    $_SESSION['logged_in_at'] = time();

                    // Check if user has preferences — flag for onboarding modal
                    $pStmt = $db->prepare(
                        'SELECT COUNT(*) FROM user_preferences WHERE user_id = ?'
                    );
                    $pStmt->execute([(int)$user['id']]);
                    $has_prefs = (int)$pStmt->fetchColumn() > 0;
                    $_SESSION['show_onboarding'] = !$has_prefs;

                    log_activity((int)$user['id'], 'login');

                    // Strict redirect validation — root-relative .php paths only, no traversal
                    $redirect = $_SESSION['redirect_after_login'] ?? '/dashboard.php';
                    unset($_SESSION['redirect_after_login']);

                    if (!preg_match('#^/[a-zA-Z0-9_\-/]+\.php$#', $redirect)
                        || str_contains($redirect, '..')) {
                        $redirect = '/dashboard.php';
                    }

                    redirect($redirect);

                } else {
                    record_failed_login();
                    $errors[] = 'Invalid username or password.';
                }
            } catch (PDOException $e) {
                error_log('[LOGIN] ' . $e->getMessage());
                $errors[] = 'A server error occurred. Please try again.';
            }
        }
    }
}

require_once 'includes/header.php';
?>

<main class="py-5">
<div class="container">
<div class="row justify-content-center">
<div class="col-md-5 col-lg-4">

    <div class="text-center mb-4">
        <div style="width:64px;height:64px;background:var(--brand-green);border-radius:16px;
                    display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
            <i class="bi bi-person-fill text-white" style="font-size:1.8rem;"></i>
        </div>
        <h1 style="font-size:1.9rem;">Welcome Back</h1>
        <p style="color:var(--brand-muted);">
            Don't have an account?
            <a href="register.php" class="text-brand fw-semibold">Register here</a>
        </p>
    </div>

    <?= flash_alert('success') ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-shield-exclamation me-2"></i>
        <?= e($errors[0]) ?>
    </div>
    <?php endif; ?>

    <div class="auth-card p-4 p-md-5">
        <form method="POST" action="login.php" novalidate>
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-person text-brand"></i></span>
                    <input type="text" id="username" name="username" class="form-control"
                           maxlength="50" placeholder="Your username"
                           autocomplete="username" required>
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-lock text-brand"></i></span>
                    <input type="password" id="password" name="password" class="form-control"
                           maxlength="255" placeholder="Your password"
                           autocomplete="current-password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-brand w-100 py-2">
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>
    </div>

</div>
</div>
</div>
</main>

<?php require_once 'includes/footer.php'; ?>