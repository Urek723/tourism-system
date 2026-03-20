<?php


require_once 'db.php';
require_once 'functions.php';

start_secure_session();

// Already logged in? Send to dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
}

$page_title = 'Create Account';
$errors     = [];
$old        = []; // Repopulate form on validation failure

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. Verify CSRF ─────────────────────────────────────────
    require_csrf();

    // ── 2. Sanitise inputs ─────────────────────────────────────
    $old['username'] = clean_string($_POST['username'] ?? '', 50);
    $old['email']    = clean_email($_POST['email']     ?? '');
    $password        = $_POST['password']         ?? '';
    $password_conf   = $_POST['password_confirm']  ?? '';

    // ── 3. Validate ────────────────────────────────────────────
    if (empty($old['username'])) {
        $errors[] = 'Username is required.';
    } elseif (!validate_username($old['username'])) {
        $errors[] = 'Username must be 3–50 characters: letters, numbers, underscores only.';
    }

    if (empty($old['email'])) {
        $errors[] = 'A valid email address is required.';
    }

    // Password strength
    $pw_errors = validate_password($password);
    $errors    = array_merge($errors, $pw_errors);

    if ($password !== $password_conf) {
        $errors[] = 'Passwords do not match.';
    }

    // ── 4. Check uniqueness (prepared statement) ──────────────
    if (empty($errors)) {
        try {
            $db   = get_db();

            $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE username = ?');
            $stmt->execute([$old['username']]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'That username is already taken. Please choose another.';
            }

            $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $stmt->execute([$old['email']]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = 'An account with that email already exists.';
            }
        } catch (PDOException $e) {
            error_log('[REGISTER UNIQUENESS CHECK] ' . $e->getMessage());
            $errors[] = 'A server error occurred. Please try again.';
        }
    }

    // ── 5. Insert new user ─────────────────────────────────────
    if (empty($errors)) {
        try {
            $db = get_db();

            // SECURITY: password_hash with bcrypt, cost 12
            $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $db->prepare(
                'INSERT INTO users (username, email, password)
                 VALUES (:username, :email, :password)'
            );
            $stmt->execute([
                ':username' => $old['username'],
                ':email'    => $old['email'],
                ':password' => $hashed,
            ]);

            set_flash('success', 'Account created! You can now sign in.');
            redirect('login.php');

        } catch (PDOException $e) {
            error_log('[REGISTER INSERT ERROR] ' . $e->getMessage());
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<main class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">

                <div class="text-center mb-4">
                    <p class="section-eyebrow">Join Us</p>
                    <h1 style="font-size:2rem;">Create Your Account</h1>
                    <p style="color:var(--brand-muted);">
                        Already have an account?
                        <a href="login.php" class="text-brand fw-semibold">Sign in here</a>
                    </p>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Please fix the following:</strong>
                    <ul class="mb-0 mt-2 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="auth-card p-4 p-md-5">
                    <form method="POST" action="register.php" novalidate
                          autocomplete="off">
                        <?= csrf_field() ?>

                        <!-- Username -->
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="bi bi-person text-brand"></i>
                                </span>
                                <input type="text" id="username" name="username"
                                       class="form-control"
                                       value="<?= eAttr($old['username'] ?? '') ?>"
                                       maxlength="50"
                                       placeholder="e.g. juan_dela_cruz"
                                       required>
                            </div>
                            <div class="form-text">
                                3–50 characters. Letters, numbers, underscores only.
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="bi bi-envelope text-brand"></i>
                                </span>
                                <input type="email" id="email" name="email"
                                       class="form-control"
                                       value="<?= eAttr($old['email'] ?? '') ?>"
                                       maxlength="254"
                                       placeholder="you@example.com"
                                       required>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="bi bi-lock text-brand"></i>
                                </span>
                                <input type="password" id="password" name="password"
                                       class="form-control"
                                       minlength="8" maxlength="255"
                                       placeholder="Create a strong password"
                                       autocomplete="new-password"
                                       required>
                            </div>
                            <div class="form-text">
                                Min. 8 characters with uppercase, lowercase, and a number.
                            </div>
                        </div>

                        <!-- Password Confirm -->
                        <div class="mb-4">
                            <label for="password_confirm" class="form-label">
                                Confirm Password
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white">
                                    <i class="bi bi-lock-fill text-brand"></i>
                                </span>
                                <input type="password" id="password_confirm"
                                       name="password_confirm"
                                       class="form-control"
                                       maxlength="255"
                                       placeholder="Repeat your password"
                                       autocomplete="new-password"
                                       required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-brand w-100 py-2">
                            <i class="bi bi-person-plus me-2"></i>Create Account
                        </button>

                        <p class="text-center mt-3 mb-0"
                           style="font-size:.8rem;color:var(--brand-muted);">
                            By registering you agree to our terms of service.
                        </p>
                    </form>
                </div>

            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
