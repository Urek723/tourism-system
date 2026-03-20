<?php

require_once 'db.php';
require_once 'functions.php';

start_secure_session();

// ── 1. Access control ─────────────────────────────────────────────────────
require_login();

// ── 2. Accept POST only ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('edit_account.php');
}

// ── 3. CSRF validation ─────────────────────────────────────────────────────
require_csrf();

// ── 4. Identify the user — ALWAYS from session, never from POST ───────────
// This prevents a user from passing a different id in POST to edit
// someone else's account (IDOR / privilege escalation).
$user_id = (int) $_SESSION['user_id'];

// ── 5. Load current DB record (needed for password verify + collision check)
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
    // Session references a deleted user — force logout
    set_flash('error', 'Account not found. Please log in again.');
    redirect('logout.php');
}

// ═══════════════════════════════════════════════════════════════════════════
// VALIDATE ALL INPUTS
// ═══════════════════════════════════════════════════════════════════════════

$errors = [];

// ── A. Username ────────────────────────────────────────────────────────────
$new_username = clean_string($_POST['username'] ?? '', 50);

if (empty($new_username)) {
    $errors[] = 'Username is required.';
} elseif (!validate_username($new_username)) {
    $errors[] = 'Username must be 3–50 characters: letters, numbers, underscores only.';
} elseif ($new_username !== $current_user['username']) {
    // Only check uniqueness if the username is actually changing
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

// ── B. Password (optional change) ─────────────────────────────────────────
$new_password     = $_POST['new_password']     ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$current_password = $_POST['current_password'] ?? '';
$changing_password = ($new_password !== '');

$new_hashed = null;   // Only set if we are actually changing the password

if ($changing_password) {

    // B1. New password strength
    $pw_errors = validate_password($new_password);   // from functions.php
    $errors    = array_merge($errors, $pw_errors);

    // B2. Confirm matches
    if ($new_password !== $confirm_password) {
        $errors[] = 'New password and confirmation do not match.';
    }

    // B3. Current password required and correct
    if (empty($current_password)) {
        $errors[] = 'Current password is required to set a new password.';
    } elseif (!password_verify($current_password, $current_user['password'])) {
        // SECURITY: password_verify() is timing-safe — prevents timing attacks
        $errors[] = 'Current password is incorrect.';
    }

    // B4. New password must differ from current
    if (empty($errors) && password_verify($new_password, $current_user['password'])) {
        $errors[] = 'New password must be different from your current password.';
    }

    // B5. Hash the new password (bcrypt, cost 12)
    if (empty($errors)) {
        $new_hashed = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
    }
}

// ── C. Preferences ────────────────────────────────────────────────────────
// Submitted values may contain anything — validate each against a
// whitelist of real category values from the database.
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
    // Non-fatal — continue without preferences update if this fails
}

// Keep only submitted values that are in the whitelist
$clean_prefs = array_filter(
    $submitted_prefs,
    fn ($p) => in_array(clean_string($p, 100), $valid_categories, true)
);
// Re-clean each value before storing
$clean_prefs = array_map(fn ($p) => clean_string($p, 100), $clean_prefs);
// Remove duplicates
$clean_prefs = array_values(array_unique($clean_prefs));

// ── If validation failed, redirect back with errors ────────────────────────
if (!empty($errors)) {
    // Store errors in session to display on edit_account.php
    // We use a separate flash key to support multiple error messages
    set_flash('error', implode(' ', $errors));
    redirect('edit_account.php');
}

// ═══════════════════════════════════════════════════════════════════════════
// APPLY CHANGES  (inside a transaction so all-or-nothing)
// ═══════════════════════════════════════════════════════════════════════════

try {
    $db->beginTransaction();

    // ── Update username (and optionally password) ──────────────────────────
    if ($changing_password) {
        // Change both username and password
        $stmt = $db->prepare(
            'UPDATE users
             SET    username = ?,
                    password = ?
             WHERE  id       = ?'
        );
        $stmt->execute([$new_username, $new_hashed, $user_id]);
    } else {
        // Change username only
        $stmt = $db->prepare(
            'UPDATE users
             SET    username = ?
             WHERE  id       = ?'
        );
        $stmt->execute([$new_username, $user_id]);
    }

    // ── Replace preferences (delete old, insert new — atomic) ─────────────
    // Delete all existing preferences for this user
    $del_stmt = $db->prepare(
        'DELETE FROM user_preferences WHERE user_id = ?'
    );
    $del_stmt->execute([$user_id]);

    // Insert the new set (if any selected)
    if (!empty($clean_prefs)) {
        $ins_stmt = $db->prepare(
            'INSERT IGNORE INTO user_preferences (user_id, category)
             VALUES (?, ?)'
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

// ═══════════════════════════════════════════════════════════════════════════
// POST-SAVE: update session + redirect
// ═══════════════════════════════════════════════════════════════════════════

// Keep session username in sync with DB
$_SESSION['username'] = $new_username;

if ($changing_password) {
    // SECURITY: regenerate session ID after a privilege-changing action
    session_regenerate_id(true);
    $_SESSION['__created'] = time();
    set_flash('success', 'Account updated and password changed successfully.');
} else {
    set_flash('success', 'Account updated successfully.');
}

// PRG redirect — prevents double-submit on browser refresh
redirect('edit_account.php');
