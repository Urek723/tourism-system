<?php

require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';

start_secure_session();

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('edit_account.php');
}

require_csrf();

// User ID always from session — never from POST (prevents IDOR)
$user_id = (int) $_SESSION['user_id'];

$current_user = null;
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT id, username, password FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$user_id]);
    $current_user = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[UPDATE ACCOUNT — FETCH] ' . $e->getMessage());
    set_flash('error', 'A server error occurred. Please try again.');
    redirect('edit_account.php');
}

if (!$current_user) {
    set_flash('error', 'Account not found. Please log in again.');
    redirect('logout.php');
}

$errors = [];

// ── A. Username ────────────────────────────────────────────────────────────
$new_username = clean_string($_POST['username'] ?? '', 50);

if (empty($new_username)) {
    $errors[] = 'Username is required.';
} elseif (!validate_username($new_username)) {
    $errors[] = 'Username must be 3–50 characters: letters, numbers, underscores only.';
} elseif ($new_username !== $current_user['username']) {
    try {
        $stmt = $db->prepare(
            'SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1'
        );
        $stmt->execute([$new_username, $user_id]);
        if ($stmt->fetch()) {
            $errors[] = 'That username is already taken. Please choose a different one.';
        }
    } catch (PDOException $e) {
        error_log('[UPDATE ACCOUNT — USERNAME CHECK] ' . $e->getMessage());
        $errors[] = 'Could not validate username. Please try again.';
    }
}

// ── B. Password (optional) ─────────────────────────────────────────────────
$new_password     = $_POST['new_password']     ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$current_password = $_POST['current_password'] ?? '';
$changing_password = ($new_password !== '');

$new_hashed = null;

if ($changing_password) {
    $pw_errors = validate_password($new_password);
    $errors    = array_merge($errors, $pw_errors);

    if ($new_password !== $confirm_password) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (empty($current_password)) {
        $errors[] = 'Current password is required to set a new password.';
    } elseif (!password_verify($current_password, $current_user['password'])) {
        $errors[] = 'Current password is incorrect.';
    }

    if (empty($errors) && password_verify($new_password, $current_user['password'])) {
        $errors[] = 'New password must be different from your current password.';
    }

    if (empty($errors)) {
        $new_hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}

// ── C. Preferences ────────────────────────────────────────────────────────
$submitted_prefs = isset($_POST['preferences']) && is_array($_POST['preferences'])
    ? $_POST['preferences']
    : [];

$valid_categories = [];
try {
    $stmt = $db->prepare(
        'SELECT DISTINCT category FROM locations WHERE is_active = 1'
    );
    $stmt->execute();
    $valid_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('[UPDATE ACCOUNT — FETCH CATS] ' . $e->getMessage());
}

$clean_prefs = array_values(array_unique(array_filter(
    array_map(fn ($p) => clean_string((string)$p, 100), $submitted_prefs),
    fn ($p) => in_array($p, $valid_categories, true)
)));

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect('edit_account.php');
}

// ── Apply changes in a transaction ────────────────────────────────────────
try {
    $db->beginTransaction();

    if ($changing_password) {
        $stmt = $db->prepare(
            'UPDATE users SET username = ?, password = ? WHERE id = ?'
        );
        $stmt->execute([$new_username, $new_hashed, $user_id]);
    } else {
        $stmt = $db->prepare('UPDATE users SET username = ? WHERE id = ?');
        $stmt->execute([$new_username, $user_id]);
    }

    $db->prepare('DELETE FROM user_preferences WHERE user_id = ?')->execute([$user_id]);

    if (!empty($clean_prefs)) {
        $ins_stmt = $db->prepare(
            'INSERT IGNORE INTO user_preferences (user_id, category) VALUES (?, ?)'
        );
        foreach ($clean_prefs as $cat) {
            $ins_stmt->execute([$user_id, $cat]);
        }
    }

    $db->commit();

} catch (PDOException $e) {
    $db->rollBack();
    error_log('[UPDATE ACCOUNT — SAVE] ' . $e->getMessage());
    set_flash('error', 'Could not save your changes. Please try again.');
    redirect('edit_account.php');
}

$_SESSION['username'] = $new_username;

log_activity($user_id, 'preference_updated');

if ($changing_password) {
    session_regenerate_id(true);
    $_SESSION['__created'] = time();
    set_flash('success', 'Account updated and password changed successfully.');
} else {
    set_flash('success', 'Account updated successfully.');
}

redirect('edit_account.php');