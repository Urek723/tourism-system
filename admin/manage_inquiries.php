<?php
require_once '../db.php';
require_once '../functions.php';
require_once '../activity_logger.php';

start_secure_session();
require_admin();
if (empty($_SESSION['admin_session'])) {
    destroy_session();
    redirect('admin_login.php');
}

$page_title = 'Manage Inquiries';
$admin_id   = (int) $_SESSION['user_id'];

// ── POST: reply and/or resolve ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $inquiry_id  = clean_int($_POST['inquiry_id'] ?? 0);
    $admin_reply = clean_string($_POST['admin_reply'] ?? '', 2000);
    $action      = $_POST['action'] ?? '';

    if ($inquiry_id <= 0) {
        set_flash('error', 'Invalid inquiry.');
        redirect('admin/manage_inquiries.php');
    }

    // Verify inquiry exists
    try {
        $db   = get_db();
        $chk  = $db->prepare('SELECT id, status FROM inquiries WHERE id = ? LIMIT 1');
        $chk->execute([$inquiry_id]);
        $inq  = $chk->fetch();
    } catch (PDOException $e) {
        error_log('[MANAGE INQ — FETCH] ' . $e->getMessage());
        set_flash('error', 'A server error occurred.');
        redirect('admin/manage_inquiries.php');
    }

    if (!$inq) {
        set_flash('error', 'Inquiry not found.');
        redirect('admin/manage_inquiries.php');
    }

    if ($action === 'reply') {
        if (empty($admin_reply)) {
            set_flash('error', 'Reply cannot be empty.');
            redirect('admin/manage_inquiries.php');
        }

        try {
            $stmt = $db->prepare(
                "UPDATE inquiries
                 SET admin_reply = ?, is_read = 1
                 WHERE id = ?"
            );
            $stmt->execute([$admin_reply, $inquiry_id]);
            log_activity($admin_id, 'admin_replied_inquiry');
            set_flash('success', 'Reply saved.');
        } catch (PDOException $e) {
            error_log('[MANAGE INQ — REPLY] ' . $e->getMessage());
            set_flash('error', 'Could not save reply.');
        }

    } elseif ($action === 'resolve') {
        try {
            $stmt = $db->prepare(
                "UPDATE inquiries SET status = 'resolved', is_read = 1 WHERE id = ?"
            );
            $stmt->execute([$inquiry_id]);
            log_activity($admin_id, 'inquiry_resolved');
            set_flash('success', 'Inquiry marked as resolved.');
        } catch (PDOException $e) {
            error_log('[MANAGE INQ — RESOLVE] ' . $e->getMessage());
            set_flash('error', 'Could not update status.');
        }

    } elseif ($action === 'reply_and_resolve') {
        if (empty($admin_reply)) {
            set_flash('error', 'Reply cannot be empty.');
            redirect('admin/manage_inquiries.php');
        }

        try {
            $stmt = $db->prepare(
                "UPDATE inquiries
                 SET admin_reply = ?, status = 'resolved', is_read = 1
                 WHERE id = ?"
            );
            $stmt->execute([$admin_reply, $inquiry_id]);
            log_activity($admin_id, 'admin_replied_inquiry');
            log_activity($admin_id, 'inquiry_resolved');
            set_flash('success', 'Reply sent and inquiry resolved.');
        } catch (PDOException $e) {
            error_log('[MANAGE INQ — REPLY+RESOLVE] ' . $e->getMessage());
            set_flash('error', 'Could not save changes.');
        }
    }

    redirect('admin/manage_inquiries.php');
}

// ── GET: list ─────────────────────────────────────────────────────────────
$filter = in_array($_GET['status'] ?? '', ['pending', 'resolved']) ? $_GET['status'] : 'all';

$inquiries = [];
try {
    $db = get_db();

    if ($filter === 'all') {
        $stmt = $db->prepare(
            'SELECT i.id, i.subject, i.message, i.name, i.email,
                    i.status, i.admin_reply, i.is_read, i.created_at,
                    l.title AS location_title,
                    u.username
             FROM   inquiries i
             LEFT   JOIN locations l ON l.id = i.location_id
             LEFT   JOIN users     u ON u.id = i.user_id
             ORDER  BY i.created_at DESC'
        );
        $stmt->execute();
    } else {
        $stmt = $db->prepare(
            'SELECT i.id, i.subject, i.message, i.name, i.email,
                    i.status, i.admin_reply, i.is_read, i.created_at,
                    l.title AS location_title,
                    u.username
             FROM   inquiries i
             LEFT   JOIN locations l ON l.id = i.location_id
             LEFT   JOIN users     u ON u.id = i.user_id
             WHERE  i.status = ?
             ORDER  BY i.created_at DESC'
        );
        $stmt->execute([$filter]);
    }
    $inquiries = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[MANAGE INQ — LIST] ' . $e->getMessage());
}

// Count per status for filter badges
$counts = ['all' => 0, 'pending' => 0, 'resolved' => 0];
try {
    $cStmt = $db->query(
        "SELECT status, COUNT(*) AS n FROM inquiries GROUP BY status"
    );
    foreach ($cStmt->fetchAll() as $row) {
        $counts[$row['status']] = (int) $row['n'];
        $counts['all'] += (int) $row['n'];
    }
} catch (PDOException $e) {
    error_log('[MANAGE INQ — COUNTS] ' . $e->getMessage());
}

require_once 'admin_header.php';
?>

<?= flash_alert('success') ?>
<?= flash_alert('error') ?>

<!-- Filter tabs -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <?php foreach (['all' => 'All', 'pending' => 'Pending', 'resolved' => 'Resolved'] as $val => $label): ?>
    <a href="?status=<?= $val ?>"
       class="btn btn-sm <?= $filter === $val ? '' : 'btn-outline-secondary' ?>"
       style="<?= $filter === $val ? 'background:#c0392b;color:#fff;' : '' ?>">
        <?= $label ?>
        <span class="badge ms-1"
              style="background:<?= $filter === $val ? 'rgba(255,255,255,.3)' : '#e2e8f0' ?>;
                     color:<?= $filter === $val ? '#fff' : '#555' ?>;">
            <?= $counts[$val] ?>
        </span>
    </a>
    <?php endforeach; ?>
</div>

<?php if (empty($inquiries)): ?>
<div class="alert alert-info">No <?= $filter !== 'all' ? e($filter) . ' ' : '' ?>inquiries found.</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($inquiries as $inq):
        $is_resolved = $inq['status'] === 'resolved';
    ?>
    <div class="stat-card p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
                <span class="fw-bold" style="font-size:.95rem;">
                    <?= e($inq['subject']) ?>
                </span>
                <div style="font-size:.78rem;color:var(--admin-muted);margin-top:.2rem;">
                    <i class="bi bi-person me-1"></i>
                    <?= !empty($inq['username']) ? e($inq['username']) : e($inq['name']) ?>
                    &nbsp;·&nbsp;
                    <i class="bi bi-envelope me-1"></i><?= e($inq['email']) ?>
                    <?php if (!empty($inq['location_title'])): ?>
                    &nbsp;·&nbsp;
                    <i class="bi bi-geo-alt me-1"></i><?= e($inq['location_title']) ?>
                    <?php endif; ?>
                    &nbsp;·&nbsp;
                    <i class="bi bi-clock me-1"></i>
                    <?= e(date('M j, Y g:i A', strtotime($inq['created_at']))) ?>
                </div>
            </div>
            <span class="badge <?= $is_resolved ? 'bg-success' : 'bg-warning text-dark' ?>">
                <?= $is_resolved ? 'Resolved' : 'Pending' ?>
            </span>
        </div>

        <!-- Message -->
        <div class="mb-3 p-3 rounded-2" style="background:#f8fafc;font-size:.9rem;
             line-height:1.75;white-space:pre-wrap;word-break:break-word;">
            <?= e($inq['message']) ?>
        </div>

        <!-- Existing reply -->
        <?php if (!empty($inq['admin_reply'])): ?>
        <div class="mb-3 p-3 rounded-2"
             style="background:#f0f9f3;border-left:4px solid #1a6b3a;">
            <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;
                        letter-spacing:.06em;color:#1a6b3a;margin-bottom:.3rem;">
                <i class="bi bi-reply me-1"></i>Your Reply
            </div>
            <p style="font-size:.88rem;line-height:1.75;margin:0;
                      white-space:pre-wrap;word-break:break-word;">
                <?= e($inq['admin_reply']) ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Reply form (always visible so reply can be updated, unless fully resolved with reply) -->
        <?php if (!$is_resolved || empty($inq['admin_reply'])): ?>
        <form method="POST" action="manage_inquiries.php">
            <?= csrf_field() ?>
            <input type="hidden" name="inquiry_id" value="<?= (int)$inq['id'] ?>">

            <div class="mb-2">
                <textarea name="admin_reply" class="form-control form-control-sm"
                          rows="3" maxlength="2000"
                          placeholder="Write a reply…"><?= e($inq['admin_reply'] ?? '') ?></textarea>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" name="action" value="reply"
                        class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-reply me-1"></i>Save Reply
                </button>
                <button type="submit" name="action" value="reply_and_resolve"
                        class="btn btn-sm"
                        style="background:#c0392b;color:#fff;">
                    <i class="bi bi-check-circle me-1"></i>Reply & Resolve
                </button>
                <?php if (!$is_resolved): ?>
                <button type="submit" name="action" value="resolve"
                        class="btn btn-sm btn-outline-secondary"
                        onclick="return confirm('Mark as resolved without a reply?')">
                    <i class="bi bi-check me-1"></i>Resolve Only
                </button>
                <?php endif; ?>
            </div>
        </form>
        <?php else: ?>
        <!-- Already resolved with reply — allow re-opening via a separate form -->
        <form method="POST" action="manage_inquiries.php">
            <?= csrf_field() ?>
            <input type="hidden" name="inquiry_id" value="<?= (int)$inq['id'] ?>">
            <div class="mb-2">
                <textarea name="admin_reply" class="form-control form-control-sm"
                          rows="3" maxlength="2000"
                          placeholder="Update reply…"><?= e($inq['admin_reply']) ?></textarea>
            </div>
            <button type="submit" name="action" value="reply"
                    class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil me-1"></i>Update Reply
            </button>
        </form>
        <?php endif; ?>

    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once 'admin_footer.php'; ?>