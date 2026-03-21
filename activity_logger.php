<?php
/**
 * activity_logger.php  [UPDATED — adds inquiry action types]
 *
 * Usage: log_activity($user_id, 'user_submitted_inquiry');
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
        // Inquiry actions
        'user_submitted_inquiry',
        'admin_replied_inquiry',
        'inquiry_resolved',
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