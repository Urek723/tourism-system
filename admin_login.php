<?php

    
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';

start_secure_session();


if (is_admin()) {
    redirect('admin/admin_panel.php');
}

$page_title = 'Admin Login';
$error      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. CSRF
    require_csrf();

// Brute-force check TEMPORARILY DISABLED for testing
// if (is_locked_out()) {
//     $error = 'Too many failed attempts. Please wait 15 minutes.';
// } else {
if (true) {
      
        $username = clean_string($_POST['username'] ?? '', 50);
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter your admin credentials.';
        } else {
            try {
                $db   = get_db();
             
                $stmt = $db->prepare(
                    "SELECT id, username, password, role
                     FROM   users
                     WHERE  username = ?
                     LIMIT  1"
                );
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                $dummy_hash = '$2y$12$invalidsaltinvalidhashinvalidhashx';
                $hash       = $user ? $user['password'] : $dummy_hash;

                if ($user && password_verify($password, $hash) && $user['role'] === 'admin') {
                    
                    clear_login_attempts();
                    session_regenerate_id(true); // Prevent session fixation

                    $_SESSION['user_id']       = (int)$user['id'];
                    $_SESSION['username']      = $user['username'];
                    $_SESSION['user_role']     = $user['role'];
                    $_SESSION['logged_in_at']  = time();
                    $_SESSION['admin_session'] = true; // Extra flag for admin pages

                    log_activity((int)$user['id'], 'admin_login');

                    redirect('admin/admin_panel.php');
                } else {
               
                    record_failed_login();
                    $error = 'Invalid credentials or insufficient privileges.';
                }
            } catch (PDOException $e) {
                error_log('[ADMIN LOGIN] ' . $e->getMessage());
                $error = 'A server error occurred. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Tupi Tourism</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: #0f2318;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }
        .login-card {
            background: #fff;
            border-radius: 18px;
            padding: 2.5rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 8px 40px rgba(0,0,0,.4);
        }
        .login-icon {
            width: 64px; height: 64px;
            background: #c0392b;
            border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.25rem;
        }
        .form-control {
            border-radius: 8px;
            padding: .65rem 1rem;
            border-color: #d0d9d2;
        }
        .form-control:focus {
            border-color: #c0392b;
            box-shadow: 0 0 0 3px rgba(192,57,43,.15);
        }
        .btn-admin {
            background: #c0392b;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: .65rem;
            font-weight: 700;
            width: 100%;
        }
        .btn-admin:hover { background: #a93226; color: #fff; }
        .back-link { color: rgba(255,255,255,.5); font-size: .82rem; text-decoration: none; }
        .back-link:hover { color: #fff; }
    </style>
</head>
<body>
<div>
    <div class="login-card">
        <div class="login-icon">
            <i class="bi bi-shield-lock-fill text-white" style="font-size:1.6rem;"></i>
        </div>
        <h4 class="text-center mb-1" style="font-weight:800;">Admin Access</h4>
        <p class="text-center text-muted mb-4" style="font-size:.85rem;">
            Tupi Tourism — Restricted Area
        </p>

        <?php if ($error): ?>
        <div class="alert alert-danger py-2" style="font-size:.875rem;">
            <i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="admin_login.php" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label for="username" class="form-label fw-semibold" style="font-size:.875rem;">
                    Username
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-person" style="color:#c0392b;"></i>
                    </span>
                    <input type="text" id="username" name="username"
                           class="form-control"
                           maxlength="50"
                           autocomplete="username"
                           required>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label fw-semibold" style="font-size:.875rem;">
                    Password
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-lock" style="color:#c0392b;"></i>
                    </span>
                    <input type="password" id="password" name="password"
                           class="form-control"
                           maxlength="255"
                           autocomplete="current-password"
                           required>
                </div>
            </div>

            <button type="submit" class="btn btn-admin">
                <i class="bi bi-shield-lock me-2"></i>Sign In to Admin Panel
            </button>
        </form>
    </div>

    <div class="text-center mt-3">
        <a href="index.php" class="back-link">
            <i class="bi bi-arrow-left me-1"></i>Back to Website
        </a>
    </div>
</div>
</body>
</html>
