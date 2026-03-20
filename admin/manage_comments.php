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

$page_title = 'Moderate Comments';
$admin_id   = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check FIRST — then read any POST values
    require_csrf();

    $comment_id  = clean_int($_POST['comment_id'] ?? 0);
    $back_filter = in_array($_POST['current_filter'] ?? '', ['pending', 'approved'])
                   ? $_POST['current_filter'] : 'pending';

    if ($comment_id > 0) {
        try {
            $db = get_db();

            if (isset($_POST['approve'])) {
                $db->prepare("UPDATE comments SET status = 'approved' WHERE id = ?")
                   ->execute([$comment_id]);
                log_activity($admin_id, 'admin_comment_approved');
                set_flash('success', 'Comment approved.');
            } elseif (isset($_POST['delete_comment'])) {
                $db->prepare('DELETE FROM comments WHERE id = ?')
                   ->execute([$comment_id]);
                log_activity($admin_id, 'admin_comment_deleted');
                set_flash('success', 'Comment deleted.');
            }
        } catch (PDOException $e) {
            error_log('[MODERATE COMMENTS] ' . $e->getMessage());
            set_flash('error', 'Action failed.');
        }
    }

    redirect('admin/manage_comments.php?filter=' . urlencode($back_filter));
}

$filter = in_array($_GET['filter'] ?? '', ['pending', 'approved']) ? $_GET['filter'] : 'pending';

$comments = [];
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT c.id, c.comment, c.status, c.created_at,
                u.username, l.title AS location_title
         FROM   comments  c
         JOIN   users     u ON u.id = c.user_id
         JOIN   locations l ON l.id = c.location_id
         WHERE  c.status = ?
         ORDER  BY c.created_at DESC'
    );
    $stmt->execute([$filter]);
    $comments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[MANAGE COMMENTS] ' . $e->getMessage());
}

require_once 'admin_header.php';
?>

<?= flash_alert('success') ?>
<?= flash_alert('error') ?>

<div class="d-flex gap-2 mb-3">
    <a href="?filter=pending"
       class="btn btn-sm <?= $filter === 'pending' ? '' : 'btn-outline-secondary' ?>"
       style="<?= $filter === 'pending' ? 'background:#c0392b;color:#fff;' : '' ?>">
        Pending
    </a>
    <a href="?filter=approved"
       class="btn btn-sm <?= $filter === 'approved' ? '' : 'btn-outline-secondary' ?>"
       style="<?= $filter === 'approved' ? 'background:#c0392b;color:#fff;' : '' ?>">
        Approved
    </a>
</div>

<?php if (empty($comments)): ?>
<div class="alert alert-info">No <?= e($filter) ?> comments.</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($comments as $c): ?>
    <div class="stat-card p-4">
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
            <div class="flex-grow-1">
                <div class="d-flex gap-2 align-items-center mb-1 flex-wrap">
                    <span class="fw-bold"><?= e($c['username']) ?></span>
                    <span style="color:var(--admin-muted);font-size:.8rem;">on</span>
                    <span style="font-size:.8rem;background:#f0f2f5;padding:.15em .5em;border-radius:4px;">
                        <?= e($c['location_title']) ?>
                    </span>
                    <span style="font-size:.75rem;color:var(--admin-muted);">
                        <?= e(date('M j, Y', strtotime($c['created_at']))) ?>
                    </span>
                </div>
                <p class="mb-0" style="line-height:1.7;"><?= e($c['comment']) ?></p>
            </div>
            <div class="d-flex gap-2 flex-shrink-0">
                <?php if ($c['status'] === 'pending'): ?>
                <form method="POST" action="manage_comments.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="comment_id"     value="<?= (int)$c['id'] ?>">
                    <input type="hidden" name="current_filter" value="<?= e($filter) ?>">
                    <button type="submit" name="approve" class="btn btn-sm btn-success">
                        <i class="bi bi-check-lg me-1"></i>Approve
                    </button>
                </form>
                <?php endif; ?>
                <form method="POST" action="manage_comments.php"
                      onsubmit="return confirm('Delete this comment?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="comment_id"     value="<?= (int)$c['id'] ?>">
                    <input type="hidden" name="current_filter" value="<?= e($filter) ?>">
                    <button type="submit" name="delete_comment" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once 'admin_footer.php'; ?>