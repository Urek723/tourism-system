<?php


require_once 'db.php';
require_once 'functions.php';

start_secure_session();


require_login();

$page_title = 'Edit Account';
$user_id    = (int) $_SESSION['user_id'];


$user = null;
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[EDIT ACCOUNT — FETCH USER] ' . $e->getMessage());
}

// Shouldn't happen for a valid session, but be safe
if (!$user) {
    set_flash('error', 'Could not load your account. Please log in again.');
    redirect('logout.php');
}

// ── Fetch all available categories ────────────────────────────────────────
$all_categories = [];
try {
    $stmt = $db->prepare(
        'SELECT DISTINCT category
         FROM   locations
         WHERE  is_active = 1
         ORDER  BY category ASC'
    );
    $stmt->execute();
    $all_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('[EDIT ACCOUNT — FETCH CATEGORIES] ' . $e->getMessage());
}

// ── Fetch user's current preferences ──────────────────────────────────────
$user_prefs = [];
try {
    $stmt = $db->prepare(
        'SELECT category FROM user_preferences WHERE user_id = ?'
    );
    $stmt->execute([$user_id]);
    $user_prefs = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('[EDIT ACCOUNT — FETCH PREFS] ' . $e->getMessage());
}

require_once 'includes/header.php';
?>

<style>
/* ── Section card ──────────────────────────────────────────── */
.section-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
    padding: 2rem;
    margin-bottom: 1.5rem;
}
.section-card h5 {
    font-size: 1rem;
    font-weight: 700;
    color: var(--brand-dark);
    margin-bottom: 1.25rem;
    padding-bottom: .75rem;
    border-bottom: 2px solid var(--brand-light);
    display: flex;
    align-items: center;
    gap: .5rem;
}

/* ── Preference toggle buttons ─────────────────────────────── */
.pref-btn-group {
    display: flex;
    flex-wrap: wrap;
    gap: .5rem;
}
.pref-btn-group input[type="checkbox"] {
    display: none; /* hidden — label acts as the button */
}
.pref-btn-group label {
    cursor: pointer;
    padding: .45rem 1.1rem;
    border-radius: 999px;
    border: 2px solid #c8dece;
    background: #fff;
    color: var(--brand-muted);
    font-size: .85rem;
    font-weight: 600;
    transition: all .15s ease;
    user-select: none;
}
.pref-btn-group label:hover {
    border-color: var(--brand-green);
    color: var(--brand-green);
    background: #f0f9f3;
}
.pref-btn-group input[type="checkbox"]:checked + label {
    background: var(--brand-green);
    border-color: var(--brand-green);
    color: #fff;
}

/* ── Password strength indicator ───────────────────────────── */
#pw-strength-bar {
    height: 4px;
    border-radius: 4px;
    transition: width .3s, background .3s;
    width: 0;
}
#pw-strength-text {
    font-size: .75rem;
    color: var(--brand-muted);
    margin-top: .25rem;
    min-height: 1rem;
}

/* ── Show / hide password toggle ───────────────────────────── */
.pw-toggle {
    cursor: pointer;
    border-left: none;
    background: #fff;
}
</style>

<main class="py-5">
<div class="container">
<div class="row justify-content-center">
<div class="col-lg-7 col-xl-6">

    <!-- Page heading -->
    <div class="mb-4">
        <p class="section-eyebrow mb-1">My Account</p>
        <h1 style="font-size:2rem;margin:0;">Edit Account</h1>
        <p style="color:var(--brand-muted);margin-top:.35rem;">
            Update your username, password, and interests.
        </p>
    </div>

    <!-- Flash messages from update_account.php redirect -->
    <?= flash_alert('success') ?>
    <?= flash_alert('error') ?>

    <form method="POST"
          action="update_account.php"
          id="edit-account-form"
          novalidate>

        <?= csrf_field() ?>

        <!-- ══════════════════════════════════════════════════ -->
        <!-- SECTION 1: Account Info                           -->
        <!-- ══════════════════════════════════════════════════ -->
        <div class="section-card">
            <h5>
                <i class="bi bi-person-circle text-brand"></i>
                Account Information
            </h5>

            <!-- Username -->
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-person text-brand"></i>
                    </span>
                    <input type="text"
                           id="username"
                           name="username"
                           class="form-control"
                           value="<?= eAttr($user['username']) ?>"
                           maxlength="50"
                           autocomplete="username"
                           required>
                </div>
                <div class="form-text">
                    3–50 characters. Letters, numbers, underscores only.
                </div>
            </div>

            <!-- Email (read-only — shown for reference) -->
            <div class="mb-1">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-envelope text-brand"></i>
                    </span>
                    <input type="email"
                           class="form-control"
                           value="<?= eAttr($user['email']) ?>"
                           disabled>
                </div>
                <div class="form-text">
                    Email cannot be changed here. Contact support if needed.
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════ -->
        <!-- SECTION 2: Change Password                        -->
        <!-- ══════════════════════════════════════════════════ -->
        <div class="section-card">
            <h5>
                <i class="bi bi-lock text-brand"></i>
                Change Password
                <span style="font-weight:400;font-size:.8rem;
                             color:var(--brand-muted);font-family:var(--font-body);">
                    — leave blank to keep current
                </span>
            </h5>

            <!-- New password -->
            <div class="mb-3">
                <label for="new_password" class="form-label">New Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-key text-brand"></i>
                    </span>
                    <input type="password"
                           id="new_password"
                           name="new_password"
                           class="form-control"
                           maxlength="255"
                           placeholder="Leave blank to keep current password"
                           autocomplete="new-password">
                    <button type="button"
                            class="input-group-text pw-toggle"
                            data-target="new_password"
                            title="Show / hide password"
                            tabindex="-1">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <!-- Strength indicator bar -->
                <div style="background:#eee;border-radius:4px;margin-top:.4rem;overflow:hidden;">
                    <div id="pw-strength-bar"></div>
                </div>
                <div id="pw-strength-text"></div>
                <div class="form-text">
                    Min. 8 characters — uppercase, lowercase, and a number.
                </div>
            </div>

            <!-- Confirm new password -->
            <div class="mb-3">
                <label for="confirm_password" class="form-label">
                    Confirm New Password
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-key-fill text-brand"></i>
                    </span>
                    <input type="password"
                           id="confirm_password"
                           name="confirm_password"
                           class="form-control"
                           maxlength="255"
                           placeholder="Repeat new password"
                           autocomplete="new-password">
                    <button type="button"
                            class="input-group-text pw-toggle"
                            data-target="confirm_password"
                            title="Show / hide password"
                            tabindex="-1">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                <div id="pw-match-msg" style="font-size:.75rem;margin-top:.25rem;"></div>
            </div>

            <!-- Current password (required only when changing) -->
            <div class="mb-1">
                <label for="current_password" class="form-label">
                    Current Password
                    <span id="current-pw-label"
                          style="font-weight:400;color:var(--brand-muted);">
                        — required when changing password
                    </span>
                </label>
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-shield-lock text-brand"></i>
                    </span>
                    <input type="password"
                           id="current_password"
                           name="current_password"
                           class="form-control"
                           maxlength="255"
                           placeholder="Enter current password to confirm changes"
                           autocomplete="current-password">
                    <button type="button"
                            class="input-group-text pw-toggle"
                            data-target="current_password"
                            title="Show / hide password"
                            tabindex="-1">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════ -->
        <!-- SECTION 3: Preferences                            -->
        <!-- ══════════════════════════════════════════════════ -->
        <div class="section-card">
            <h5>
                <i class="bi bi-heart text-brand"></i>
                My Interests
                <span style="font-weight:400;font-size:.8rem;
                             color:var(--brand-muted);font-family:var(--font-body);">
                    — personalises your destination feed
                </span>
            </h5>

            <?php if (empty($all_categories)): ?>
                <p style="color:var(--brand-muted);font-size:.9rem;">
                    No categories available yet.
                </p>
            <?php else: ?>
                <div class="pref-btn-group" role="group" aria-label="Select your interests">
                    <?php foreach ($all_categories as $cat):
                        $cat_id   = 'pref_' . preg_replace('/[^a-z0-9]/i', '_', $cat);
                        $checked  = in_array($cat, $user_prefs, true) ? 'checked' : '';
                    ?>
                        <input type="checkbox"
                               id="<?= eAttr($cat_id) ?>"
                               name="preferences[]"
                               value="<?= eAttr($cat) ?>"
                               <?= $checked ?>>
                        <label for="<?= eAttr($cat_id) ?>">
                            <?= e($cat) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="form-text mt-2">
                    Select the types of destinations you enjoy. Leave all unchecked to
                    see everything.
                </div>
            <?php endif; ?>
        </div>

        <!-- ── Submit buttons ────────────────────────────── -->
        <div class="d-flex gap-3 flex-wrap">
            <button type="submit" class="btn btn-brand px-4 py-2">
                <i class="bi bi-save me-2"></i>Save Changes
            </button>
            <a href="dashboard.php" class="btn btn-outline-secondary px-4 py-2">
                Cancel
            </a>
        </div>

    </form>
</div>
</div>
</div>
</main>

<!-- ── Client-side UX helpers (no security logic here) ───────── -->
<script>
(function () {
    'use strict';

    // ── Show / hide password toggles ──────────────────────────
    document.querySelectorAll('.pw-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.target);
            var icon  = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });
    });

    // ── Password strength meter ────────────────────────────────
    var newPwInput = document.getElementById('new_password');
    var bar        = document.getElementById('pw-strength-bar');
    var txt        = document.getElementById('pw-strength-text');

    function measureStrength(pw) {
        if (!pw) return { score: 0, label: '', color: '' };
        var score = 0;
        if (pw.length >= 8)               score++;
        if (pw.length >= 12)              score++;
        if (/[A-Z]/.test(pw))             score++;
        if (/[a-z]/.test(pw))             score++;
        if (/[0-9]/.test(pw))             score++;
        if (/[^A-Za-z0-9]/.test(pw))      score++;
        if (score <= 2) return { score: 2, label: 'Weak',   color: '#e74c3c' };
        if (score <= 4) return { score: 4, label: 'Fair',   color: '#f39c12' };
        return             { score: 6, label: 'Strong', color: '#27ae60' };
    }

    newPwInput.addEventListener('input', function () {
        var r = measureStrength(this.value);
        if (!this.value) {
            bar.style.width = '0';
            txt.textContent = '';
            return;
        }
        bar.style.width      = (r.score / 6 * 100) + '%';
        bar.style.background = r.color;
        txt.textContent      = r.label;
        txt.style.color      = r.color;
        checkMatch();
    });

    // ── Password match checker ─────────────────────────────────
    var confirmPwInput = document.getElementById('confirm_password');
    var matchMsg       = document.getElementById('pw-match-msg');

    function checkMatch() {
        if (!confirmPwInput.value) { matchMsg.textContent = ''; return; }
        if (newPwInput.value === confirmPwInput.value) {
            matchMsg.textContent = '✓ Passwords match';
            matchMsg.style.color = '#27ae60';
        } else {
            matchMsg.textContent = '✗ Passwords do not match';
            matchMsg.style.color = '#e74c3c';
        }
    }

    confirmPwInput.addEventListener('input', checkMatch);

    // ── Client-side form validation (server ALWAYS re-validates) ─
    document.getElementById('edit-account-form').addEventListener('submit', function (e) {
        var newPw  = newPwInput.value;
        var confPw = confirmPwInput.value;
        var curPw  = document.getElementById('current_password').value;

        if (newPw && newPw !== confPw) {
            e.preventDefault();
            alert('New passwords do not match.');
            confirmPwInput.focus();
            return;
        }

        if (newPw && !curPw) {
            e.preventDefault();
            alert('Please enter your current password to confirm the change.');
            document.getElementById('current_password').focus();
        }
    });
}());
</script>

<?php require_once 'includes/footer.php'; ?>
