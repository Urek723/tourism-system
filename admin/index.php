<?php
/**
 * admin/index.php — Admin Panel Dashboard
 * Security: require_admin(), PDO, e(), CSRF on all forms.
 */

require_once '../db.php';
require_once '../functions.php';

start_secure_session();
require_admin();

$page_title = 'Admin Panel';

// Stats
$stats = [];
try {
    $db = get_db();
    foreach ([
        'users'           => 'SELECT COUNT(*) FROM users',
        'locations'       => 'SELECT COUNT(*) FROM locations WHERE is_active = 1',
        'pending_comments'=> "SELECT COUNT(*) FROM comments WHERE status = 'pending'",
        'bookings'        => "SELECT COUNT(*) FROM bookings WHERE status = 'pending'",
    ] as $key => $sql) {
        $stats[$key] = (int)$db->query($sql)->fetchColumn();
    }
} catch (PDOException $e) {
    error_log('[ADMIN STATS] ' . $e->getMessage());
}

require_once '../includes/header.php';
?>

<main class="py-5">
<div class="container">

    <div class="mb-4">
        <p class="section-eyebrow mb-1"><i class="bi bi-shield-lock me-1"></i>Admin</p>
        <h1 style="font-size:1.9rem;margin:0;">Admin Panel</h1>
    </div>

    <?= flash_alert('success') ?>

    <!-- Navigation pills -->
    <div class="d-flex gap-2 flex-wrap mb-4">
        <?php
        $nav = [
            ['manage_users.php',     'bi-people-fill',    'Manage Users'],
            ['manage_locations.php', 'bi-geo-alt-fill',   'Manage Locations'],
            ['manage_comments.php',  'bi-chat-dots-fill', 'Moderate Comments'],
            ['manage_bookings.php',  'bi-calendar-check', 'Manage Bookings'],
        ];
        foreach ($nav as [$href, $icon, $label]):
        ?>
        <a href="<?= $href ?>" class="btn btn-outline-secondary">
            <i class="bi <?= $icon ?> me-2"></i><?= $label ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Stats -->
    <div class="row g-3">
        <?php
        $cards = [
            ['bi-people-fill',   'Total Users',       $stats['users'],            '#dce8f5', '#1a4fa0'],
            ['bi-geo-alt-fill',  'Active Locations',  $stats['locations'],        '#d4e8da', '#1a6b3a'],
            ['bi-chat-dots-fill','Pending Comments',  $stats['pending_comments'], '#fff3cd', '#856404'],
            ['bi-calendar-check','Pending Bookings',  $stats['bookings'],         '#f8d7da', '#58151c'],
        ];
        foreach ($cards as [$icon, $label, $value, $bg, $color]):
        ?>
        <div class="col-6 col-md-3">
            <div class="p-4 rounded-3 text-center"
                 style="background:#fff;box-shadow:0 1px 8px rgba(0,0,0,.06);">
                <div style="width:48px;height:48px;background:<?= $bg ?>;border-radius:12px;
                            display:flex;align-items:center;justify-content:center;margin:0 auto .75rem;">
                    <i class="bi <?= $icon ?>" style="color:<?= $color ?>;font-size:1.2rem;"></i>
                </div>
                <div style="font-size:1.7rem;font-weight:800;font-family:var(--font-display);">
                    <?= $value ?>
                </div>
                <div style="font-size:.75rem;text-transform:uppercase;
                            letter-spacing:.06em;color:var(--brand-muted);font-weight:600;">
                    <?= e($label) ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

</div>
</main>

<?php require_once '../includes/footer.php'; ?>