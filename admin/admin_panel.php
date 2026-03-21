<?php


require_once '../db.php';
require_once '../functions.php';
require_once '../activity_logger.php';

start_secure_session();
date_default_timezone_set('Asia/Manila');
require_admin();

// Extra guard: must have come through admin_login.php
if (empty($_SESSION['admin_session'])) {
    destroy_session();
    redirect('admin_login.php');
}

$page_title = 'Dashboard';

$stats = ['users' => 0, 'locations' => 0, 'pending_comments' => 0,
          'bookings' => 0, 'ratings' => 0, 'favorites' => 0];
$recent_logs = [];

try {
    $db = get_db();

    $queries = [
        'users'            => 'SELECT COUNT(*) FROM users',
        'locations'        => 'SELECT COUNT(*) FROM locations WHERE is_active = 1',
        'pending_comments' => "SELECT COUNT(*) FROM comments WHERE status = 'pending'",
        'bookings'         => "SELECT COUNT(*) FROM bookings WHERE status = 'pending'",
        'ratings'          => 'SELECT COUNT(*) FROM ratings',
        'favorites'        => 'SELECT COUNT(*) FROM favorites',
    ];
    foreach ($queries as $key => $sql) {
        $stats[$key] = (int)$db->query($sql)->fetchColumn();
    }

    // Recent activity
    $stmt = $db->prepare(
        'SELECT a.action, a.created_at, u.username
         FROM   activity_logs a
         JOIN   users         u ON u.id = a.user_id
         ORDER  BY a.created_at DESC
         LIMIT  15'
    );
    $stmt->execute();
    $recent_logs = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[ADMIN PANEL STATS] ' . $e->getMessage());
}

require_once 'admin_header.php';
?>

<?= flash_alert('success') ?>
<?= flash_alert('error') ?>

<!-- Stat cards -->
<div class="row g-3 mb-4">
<?php
$cards = [
    ['bi-people-fill',    'Users',            $stats['users'],            '#dce8f5', '#1a4fa0', 'manage_users.php'],
    ['bi-geo-alt-fill',   'Active Locations', $stats['locations'],        '#d4e8da', '#1a6b3a', 'manage_locations.php'],
    ['bi-chat-dots-fill', 'Pending Comments', $stats['pending_comments'], '#fff3cd', '#856404', 'manage_comments.php'],
    ['bi-calendar-check', 'Pending Bookings', $stats['bookings'],         '#fce8e8', '#c0392b', 'manage_bookings.php'],
    ['bi-star-fill',      'Total Ratings',    $stats['ratings'],          '#fff8e7', '#8a6a10', '#'],
    ['bi-heart-fill',     'Total Favorites',  $stats['favorites'],        '#f8e8f8', '#8b1a8b', '#'],
];
foreach ($cards as [$icon, $label, $value, $bg, $color, $href]):
?>
<div class="col-6 col-md-4 col-xl-2">
    <a href="<?= $href ?>" class="text-decoration-none">
        <div class="stat-card text-center">
            <div style="width:44px;height:44px;background:<?= $bg ?>;border-radius:10px;
                        display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;">
                <i class="bi <?= $icon ?>" style="color:<?= $color ?>;font-size:1.1rem;"></i>
            </div>
            <div style="font-size:1.6rem;font-weight:800;color:#1a1f2e;line-height:1;">
                <?= $value ?>
            </div>
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;
                        color:var(--admin-muted);font-weight:600;margin-top:.25rem;">
                <?= e($label) ?>
            </div>
        </div>
    </a>
</div>
<?php endforeach; ?>
</div>

<!-- Recent activity -->
<div class="stat-card">
    <h6 style="font-weight:700;margin-bottom:1rem;">
        <i class="bi bi-journal-text me-2" style="color:var(--admin-red);"></i>
        Recent Activity
    </h6>
    <?php if (empty($recent_logs)): ?>
        <p class="text-muted mb-0" style="font-size:.85rem;">No activity recorded yet.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0 admin-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recent_logs as $log): ?>
            <tr>
                <td class="fw-semibold"><?= e($log['username']) ?></td>
                <td>
                    <span style="font-family:monospace;font-size:.8rem;
                                 background:#f0f2f5;padding:.15em .5em;border-radius:4px;">
                        <?= e($log['action']) ?>
                    </span>
                </td>
                <td style="color:var(--admin-muted);font-size:.82rem;">
                    <?= e(date('M j, Y g:i A', strtotime($log['created_at'] . ' UTC'))) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-2">
        <a href="activity_logs.php" style="font-size:.8rem;color:var(--admin-red);">
            View all logs →
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once 'admin_footer.php'; ?>
