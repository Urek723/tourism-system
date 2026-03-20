<?php


require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';
start_secure_session();

// ── Access control ────────────────────────────────────────────────────────
require_login();   // Redirects to login.php if not authenticated

// ── Only accept POST ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('locations.php');
}

// ── 1. CSRF validation ────────────────────────────────────────────────────
require_csrf();

// ── 2. Sanitise and validate inputs ───────────────────────────────────────

// location_id — must be a positive integer
$location_id = clean_int($_POST['location_id'] ?? 0);

// comment text — strip HTML tags, trim whitespace, cap at 1 000 chars
$comment_text = clean_string($_POST['comment'] ?? '', 1000);

// Build error list
$errors = [];

if ($location_id === 0) {
    $errors[] = 'Invalid location.';
}

if (empty($comment_text)) {
    $errors[] = 'Comment cannot be empty.';
} elseif (mb_strlen($comment_text) < 3) {
    $errors[] = 'Comment must be at least 3 characters.';
} elseif (mb_strlen($comment_text) > 1000) {
    $errors[] = 'Comment cannot exceed 1,000 characters.';
}

// If validation failed, go back with error message in session flash
if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect('location.php?id=' . $location_id . '#comments');
}

// ── 3. Verify the location actually exists (whitelist check) ──────────────
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT id FROM locations WHERE id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$location_id]);

    if (!$stmt->fetch()) {
        set_flash('error', 'The destination you commented on no longer exists.');
        redirect('locations.php');
    }
} catch (PDOException $e) {
    error_log('[ADD COMMENT — LOCATION CHECK] ' . $e->getMessage());
    set_flash('error', 'A server error occurred. Please try again.');
    redirect('location.php?id=' . $location_id . '#comments');
}

// ── 4. Insert comment — PDO prepared statement ────────────────────────────
try {
    $user_id = (int) $_SESSION['user_id'];

    $stmt = $db->prepare(
        'INSERT INTO comments (user_id, location_id, comment)
         VALUES (:user_id, :location_id, :comment)'
    );
    $stmt->execute([
        ':user_id'     => $user_id,
        ':location_id' => $location_id,
        ':comment'     => $comment_text,
    ]);
    log_activity($user_id, 'comment_posted');
    set_flash('success', 'Your comment was posted successfully.');

} catch (PDOException $e) {
    error_log('[ADD COMMENT — INSERT] ' . $e->getMessage());
    set_flash('error', 'Could not post your comment. Please try again.');
}

// ── 5. PRG redirect back to the location page ─────────────────────────────
redirect('location.php?id=' . $location_id . '#comments');
