<?php
require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';

start_secure_session();
header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$token = $body['csrf_token'] ?? '';
if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF validation failed']);
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$submitted = is_array($body['categories'] ?? null) ? $body['categories'] : [];

try {
    $db = get_db();

    $stmt = $db->prepare(
        'SELECT DISTINCT category FROM locations WHERE is_active = 1'
    );
    $stmt->execute();
    $valid = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $clean = array_values(array_unique(
        array_filter(
            array_map(fn($c) => clean_string((string)$c, 100), $submitted),
            fn($c) => in_array($c, $valid, true)
        )
    ));

    $db->beginTransaction();

    $db->prepare('DELETE FROM user_preferences WHERE user_id = ?')->execute([$user_id]);

    if (!empty($clean)) {
        $ins = $db->prepare(
            'INSERT IGNORE INTO user_preferences (user_id, category) VALUES (?, ?)'
        );
        foreach ($clean as $cat) {
            $ins->execute([$user_id, $cat]);
        }
    }

    $db->commit();

    unset($_SESSION['show_onboarding']);
    log_activity($user_id, 'preference_updated');

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('[SAVE PREFS] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}