<?php


require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';
start_secure_session();

// ── Access control ────────────────────────────────────────────────────────
require_login();

// ── Only accept POST ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('locations.php');
}

// ── 1. CSRF validation ────────────────────────────────────────────────────
require_csrf();

// ── 2. Sanitise and validate ──────────────────────────────────────────────

$location_id = clean_int($_POST['location_id'] ?? 0);

// Rating must be a whole number 1–5 — clean_int strips non-numerics first
$rating = clean_int($_POST['rating'] ?? 0);

$errors = [];

if ($location_id === 0) {
    $errors[] = 'Invalid location.';
}

// SECURITY: whitelist validation — only exact values 1–5 are accepted
if (!in_array($rating, [1, 2, 3, 4, 5], true)) {
    $errors[] = 'Rating must be a number between 1 and 5.';
}

if (!empty($errors)) {
    set_flash('error', implode(' ', $errors));
    redirect('location.php?id=' . $location_id . '#rating');
}

// ── 3. Verify location exists ─────────────────────────────────────────────
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT id FROM locations WHERE id = ? AND is_active = 1 LIMIT 1'
    );
    $stmt->execute([$location_id]);

    if (!$stmt->fetch()) {
        set_flash('error', 'That destination no longer exists.');
        redirect('locations.php');
    }
} catch (PDOException $e) {
    error_log('[ADD RATING — LOCATION CHECK] ' . $e->getMessage());
    set_flash('error', 'A server error occurred. Please try again.');
    redirect('location.php?id=' . $location_id . '#rating');
}

// ── 4. Upsert rating — one per user per location ──────────────────────────
// INSERT ... ON DUPLICATE KEY UPDATE lets a user change their rating.
// The UNIQUE KEY (user_id, location_id) in the schema prevents duplicates.
try {
    $user_id = (int) $_SESSION['user_id'];

    $stmt = $db->prepare(
        'INSERT INTO ratings (user_id, location_id, rating)
         VALUES (:user_id, :location_id, :rating)
         ON DUPLICATE KEY UPDATE
             rating     = VALUES(rating),
             created_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':user_id'     => $user_id,
        ':location_id' => $location_id,
        ':rating'      => $rating,
    ]);
    log_activity($user_id, 'rating_submitted');
    set_flash('success', 'Your rating has been saved. Thank you!');

} catch (PDOException $e) {
    error_log('[ADD RATING — UPSERT] ' . $e->getMessage());
    set_flash('error', 'Could not save your rating. Please try again.');
}

// ── 5. PRG redirect ───────────────────────────────────────────────────────
redirect('location.php?id=' . $location_id . '#rating');
