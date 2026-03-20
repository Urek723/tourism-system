<?php
/**
 * admin/manage_bookings.php — Booking Management
 *
 * Security: require_admin + admin_session, CSRF, clean_int, PDO, e().
 */

require_once '../db.php';
require_once '../functions.php';
require_once '../activity_logger.php';

start_secure_session();
require_admin();
if (empty($_SESSION['admin_session'])) { destroy_session(); redirect('admin_login.php'); }

$page_title = 'Manage Bookings';
$admin_id   = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    require_csrf();
    $book_id   = clean_int($_POST['booking_id'] ?? 0);
    $new_status = in_array($_POST['status'] ?? '', ['pending','confirmed','cancelled'])
                  ? $_POST['status'] : null;

    if ($book_id > 0 && $new_status) {
        try {
            $db = get_db();
            $db->prepare('UPDATE bookings SET status = ? WHERE id = ?')
               ->execute([$new_status, $book_id]);
            log_activity($admin_id, 'admin_booking_status_changed');
            set_flash('success', 'Booking status updated to ' . ucfirst($new_status) . '.');
        } catch (PDOException $e) {
            error_log('[ADMIN UPDATE BOOKING] ' . $e->getMessage());
            set_flash('error', 'Could not update booking.');
        }
    }
    redirect('admin/manage_bookings.php');
}

$filter   = in_array($_GET['status'] ?? '', ['pending','confirmed','cancelled','all']) ? $_GET['status'] : 'all';
$bookings = [];
try {
    $db = get_db();
    if ($filter === 'all') {
        $stmt = $db->prepare(
            'SELECT b.id, b.date, b.status, b.created_at,
                    u.username, l.title AS location_title
             FROM   bookings  b
             JOIN   users     u ON u.id = b.user_id
             JOIN   locations l ON l.id = b.location_id
             ORDER  BY b.created_at DESC'
        );
        $stmt->execute();
    } else {
        $stmt = $db->prepare(
            'SELECT b.id, b.date, b.status, b.created_at,
                    u.username, l.title AS location_title
             FROM   bookings  b
             JOIN   users     u ON u.id = b.user_id
             JOIN   locations l ON l.id = b.location_id
             WHERE  b.status = ?
             ORDER  BY b.created_at DESC'
        );
        $stmt->execute([$filter]);
    }
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[ADMIN LIST BOOKINGS] ' . $e->getMessage());
}

require_once 'admin_header.php';
?>

<?= flash_alert('success') ?>
<?= flash_alert('error') ?>

<div class="d-flex gap-2 mb-3 flex-wrap">
    <?php foreach (['all','pending','confirmed','cancelled'] as $s): ?>
    <a href="?status=<?= $s ?>"
       class="btn btn-sm <?= $filter === $s ? '' : 'btn-outline-secondary' ?>"
       style="<?= $filter === $s ? 'background:#c0392b;color:#fff;' : '' ?>">
        <?= ucfirst($s) ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="stat-card p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0 admin-table">
    <thead>
        <tr>
            <th class="px-3">User</th>
            <th>Location</th>
            <th>Visit Date</th>
            <th>Status</th>
            <th>Booked On</th>
            <th class="text-end pe-3">Change Status</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($bookings as $b): ?>
    <?php
        $badge_map = [
            'pending'   => 'bg-warning text-dark',
            'confirmed' => 'bg-success',
            'cancelled' => 'bg-secondary',
        ];
    ?>
    <tr>
        <td class="px-3 fw-semibold"><?= e($b['username']) ?></td>
        <td><?= e($b['location_title']) ?></td>
        <td><?= e(date('M j, Y', strtotime($b['date']))) ?></td>
        <td>
            <span class="badge <?= $badge_map[$b['status']] ?? 'bg-secondary' ?>">
                <?= e(ucfirst($b['status'])) ?>
            </span>
        </td>
        <td style="color:var(--admin-muted);font-size:.82rem;">
            <?= e(date('M j, Y', strtotime($b['created_at']))) ?>
        </td>
        <td class="text-end pe-3">
            <form method="POST" action="manage_bookings.php" class="d-flex gap-1 justify-content-end">
                <?= csrf_field() ?>
                <input type="hidden" name="update_status" value="1">
                <input type="hidden" name="booking_id"    value="<?= (int)$b['id'] ?>">
                <select name="status" class="form-select form-select-sm" style="width:130px;">
                    <?php foreach (['pending','confirmed','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= $b['status'] === $s ? 'selected' : '' ?>>
                        <?= ucfirst($s) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-check-lg"></i>
                </button>
            </form>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<?php require_once 'admin_footer.php'; ?>
