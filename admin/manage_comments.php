<?php

require_once '../db.php';
require_once '../functions.php';

start_secure_session();
require_admin();

$page_title = 'Moderate Comments';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $comment_id   = clean_int($_POST['comment_id'] ?? 0);
    $back_filter  = in_array($_POST['current_filter'] ?? '', ['pending', 'approved'])
                    ? $_POST['current_filter'] : 'pending';

    if ($comment_id > 0) {
        try {
            $db = get_db();

            if (isset($_POST['approve'])) {
                $stmt = $db->prepare("UPDATE comments SET status = 'approved' WHERE id = ?");
                $stmt->execute([$comment_id]);
                set_flash('success', 'Comment approved.');
            } elseif (isset($_POST['delete_comment'])) {
                $stmt = $db->prepare('DELETE FROM comments WHERE id = ?');
                $stmt->execute([$comment_id]);
                set_flash('success', 'Comment deleted.');
            }
        } catch (PDOException $e) {
            error_log('[MODERATE COMMENTS] ' . $e->getMessage());
            set_flash('error', 'Action failed.');
        }
    }

    // FIX: removed 'admin/' prefix — file is already inside admin/ folder
    redirect('manage_comments.php?filter=' . $back_filter);
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

require_once '../includes/header.php';
?>

<main class="py-5">
<div class="container">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <p class="section-eyebrow mb-1"><a href="index.php" class="text-brand">Admin</a></p>
            <h1 style="font-size:1.9rem;margin:0;">Moderate Comments</h1>
        </div>
        <div class="d-flex gap-2">
            <a href="?filter=pending"
               class="btn btn-sm <?= $filter === 'pending' ? 'btn-brand' : 'btn-outline-secondary' ?>">
                Pending
            </a>
            <a href="?filter=approved"
               class="btn btn-sm <?= $filter === 'approved' ? 'btn-brand' : 'btn-outline-secondary' ?>">
                Approved
            </a>
        </div>
    </div>

    <?= flash_alert('success') ?>
    <?= flash_alert('error') ?>

    <?php if (empty($comments)): ?>
    <div class="alert alert-info">No <?= e($filter) ?> comments.</div>
    <?php else: ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($comments as $c): ?>
        <div class="p-4 rounded-3" style="background:#fff;box-shadow:0 1px 8px rgba(0,0,0,.06);">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div class="flex-grow-1">
                    <div class="d-flex gap-2 align-items-center mb-1 flex-wrap">
                        <span class="fw-bold"><?= e($c['username']) ?></span>
                        <span style="color:var(--brand-muted);font-size:.8rem;">on</span>
                        <span class="badge-category"><?= e($c['location_title']) ?></span>
                        <span style="font-size:.75rem;color:var(--brand-muted);">
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

</div>
</main>

<?php require_once '../includes/footer.php'; ?>