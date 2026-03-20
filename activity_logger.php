<?php
/**
 * activity_logger.php  [UPDATED — adds admin action types]
 *
 * Drop-in replacement for the v4 activity_logger.php.
 * Extended whitelist now covers all admin CRUD actions.
 *
 * Usage: log_activity($user_id, 'admin_login');
 */

function log_activity(int $user_id, string $action): void
{
    if ($user_id <= 0) return;

    $allowed = [
        // User actions
        'login', 'logout',
        'comment_posted', 'rating_submitted',
        'preference_updated',
        'favorite_added', 'favorite_removed',
        'trip_added', 'booking_created', 'booking_cancelled',
        // Admin actions
        'admin_login', 'admin_logout',
        'admin_user_deleted',
        'admin_location_added', 'admin_location_edited', 'admin_location_deleted',
        'admin_comment_approved', 'admin_comment_deleted',
        'admin_booking_status_changed',
    ];

    if (!in_array($action, $allowed, true)) return;

    try {
        $db   = get_db();
        $stmt = $db->prepare(
            'INSERT INTO activity_logs (user_id, action) VALUES (?, ?)'
        );
        $stmt->execute([$user_id, $action]);
    } catch (PDOException $e) {
        error_log('[ACTIVITY LOG] ' . $e->getMessage());
    }
}
