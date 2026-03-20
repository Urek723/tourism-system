<?php
/**
 * admin/activity_logs.php — Activity Log Viewer
 */

require_once '../db.php';
require_once '../functions.php';

start_secure_session();
require_admin();
if (empty($_SESSION['admin_session'])) {
    destroy_session();
    redirect('admin_login.php');
}

$page_title = 'Activity Logs';

$page     = max(1, clean_int($_GET['page'] ?? 1));
$per_page = 30;
$offset   = ($page - 1) * $per_page;

$filter_action = clean_string($_GET['action'] ?? '', 50);

$logs  = [];
$total = 0;

try {
    $db = get_db();

    if (!empty($filter_action)) {
        $like = '%' . $filter_action . '%';
        $cStmt = $db->prepare(
            'SELECT COUNT(*) FROM activity_logs WHERE action LIKE ?'
        );
        $cStmt->execute([$like]);
        $total = (int)$cStmt->fetchColumn();

        $stmt = $db->prepare(
            'SELECT a.id, a.action, a.created_at, u.username
             FROM   activity_logs a
             JOIN   users         u ON u.id = a.user_id
             WHERE  a.action LIKE ?
             ORDER  BY a.created_at DESC
             LIMIT  ? OFFSET ?'
        );
        $stmt->execute([$like, $per_page, $offset]);
    } else {
        $total = (int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
        $stmt  = $db->prepare(
            'SELECT a.id, a.action, a.created_at, u.username
             FROM   activity_logs a
             JOIN   users         u ON u.id = a.user_id
             ORDER  BY a.created_at DESC
             LIMIT  ? OFFSET ?'
        );
        $stmt->execute([$per_page, $offset]);
    }
    $logs = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[ADMIN ACTIVITY LOGS] ' . $e->getMessage());
}

$total_pages = (int)ceil($total / $per_page);

function action_badge(string $action): string
{
    if (str_starts_with($action, 'admin_')) return 'background:#fce8e8;color:#c0392b;';
    if (str_contains($action, 'login'))     return 'background:#d4e8da;color:#1a6b3a;';
    if (str_contains($action, 'logout'))    return 'background:#e8ede9;color:#4a5a4e;';
    if (str_contains($action, 'deleted'))   return 'background:#fff3cd;color:#856404;';
    return 'background:#e8f0fc;color:#1a4fa0;';
}

require_once 'admin_header.php';
?>

<!-- Filter -->
<form method="GET" action="activity_logs.php" class="d-flex gap-2 mb-3">
    <input type="text" name="action" class="form-control form-control-sm"
           placeholder="Filter by action (e.g. login, admin_…)"
           value="<?= eAttr($filter_action) ?>" maxlength="50" style="width:260px;">
    <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
    <?php if ($filter_action): ?>
    <a href="activity_logs.php" class="btn btn-sm btn-outline-secondary">Clear</a>
    <?php endif; ?>
    <span class="ms-auto align-self-center" style="font-size:.8rem;color:var(--admin-muted);">
        <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?>
    </span>
</form>

<div class="stat-card p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0 admin-table">
    <thead>
        <tr>
            <th class="px-3">#</th>
            <th>User</th>
            <th>Action</th>
            <th>Timestamp</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
    <tr>
        <td class="px-3" style="color:var(--admin-muted);font-size:.78rem;">
            <?= (int)$log['id'] ?>
        </td>
        <td class="fw-semibold"><?= e($log['username']) ?></td>
        <td>
            <span style="font-family:monospace;font-size:.8rem;padding:.2em .6em;
                         border-radius:4px;<?= action_badge($log['action']) ?>">
                <?= e($log['action']) ?>
            </span>
        </td>
        <td style="color:var(--admin-muted);font-size:.82rem;">
            <?= e(date('M j, Y g:i:s A', strtotime($log['created_at']))) ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<div class="d-flex justify-content-center mt-4 gap-1 flex-wrap">
    <?php if ($page > 1): ?>
    <a href="?page=<?= $page-1 ?><?= $filter_action ? '&action='.urlencode($filter_action) : '' ?>"
       class="btn btn-sm btn-outline-secondary">« Prev</a>
    <?php endif; ?>

    <?php
    $start = max(1, $page - 2);
    $end   = min($total_pages, $page + 2);
    for ($p = $start; $p <= $end; $p++):
    ?>
    <a href="?page=<?= $p ?><?= $filter_action ? '&action='.urlencode($filter_action) : '' ?>"
       class="btn btn-sm <?= $p === $page ? '' : 'btn-outline-secondary' ?>"
       style="<?= $p === $page ? 'background:#c0392b;color:#fff;' : '' ?>">
        <?= $p ?>
    </a>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
    <a href="?page=<?= $page+1 ?><?= $filter_action ? '&action='.urlencode($filter_action) : '' ?>"
       class="btn btn-sm btn-outline-secondary">Next »</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once 'admin_footer.php'; ?>