<?php
require_once 'db.php';
require_once 'functions.php';

start_secure_session();
require_login();

$page_title = 'My Inquiries';
$user_id    = (int) $_SESSION['user_id'];

$inquiries = [];
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT i.id, i.subject, i.message, i.status, i.admin_reply,
                i.is_read, i.created_at,
                l.title AS location_title
         FROM   inquiries i
         LEFT   JOIN locations l ON l.id = i.location_id
         WHERE  i.user_id = ?
         ORDER  BY i.created_at DESC'
    );
    $stmt->execute([$user_id]);
    $inquiries = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[MY INQUIRIES — FETCH] ' . $e->getMessage());
}

require_once 'includes/header.php';
?>

<main>
<section style="background:var(--brand-dark);padding:3.5rem 0 2.5rem;">
    <div class="container">
        <p class="section-eyebrow" style="color:var(--brand-gold);">My Account</p>
        <h1 style="color:#fff;font-size:2.5rem;margin-bottom:.5rem;">My Inquiries</h1>
        <p style="color:rgba(255,255,255,.6);margin:0;">
            <?= count($inquiries) ?> inquiry<?= count($inquiries) !== 1 ? 's' : '' ?> submitted
        </p>
    </div>
</section>

<section class="py-5">
<div class="container">

    <?= flash_alert('success') ?>
    <?= flash_alert('error') ?>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Inquiry History</h5>
        <a href="inquiries.php" class="btn btn-brand btn-sm">
            <i class="bi bi-plus me-1"></i>New Inquiry
        </a>
    </div>

    <?php if (empty($inquiries)): ?>
    <div class="text-center py-5">
        <i class="bi bi-envelope text-brand" style="font-size:3rem;opacity:.4;"></i>
        <h4 class="mt-3">No inquiries yet</h4>
        <p style="color:var(--brand-muted);">Submit an inquiry and we'll get back to you.</p>
        <a href="inquiries.php" class="btn btn-brand mt-2">Submit Inquiry</a>
    </div>
    <?php else: ?>
    <div class="d-flex flex-column gap-3">
        <?php foreach ($inquiries as $inq):
            $is_resolved = $inq['status'] === 'resolved';
        ?>
        <div class="p-4 rounded-3"
             style="background:#fff;box-shadow:0 1px 8px rgba(0,0,0,.06);">

            <!-- Header row -->
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-2">
                <div>
                    <span class="fw-bold" style="font-size:1rem;">
                        <?= e($inq['subject']) ?>
                    </span>
                    <?php if (!empty($inq['location_title'])): ?>
                    <span style="font-size:.8rem;color:var(--brand-muted);margin-left:.5rem;">
                        <i class="bi bi-geo-alt me-1"></i><?= e($inq['location_title']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge <?= $is_resolved ? 'bg-success' : 'bg-warning text-dark' ?>"
                          style="font-size:.72rem;">
                        <?= $is_resolved ? 'Resolved' : 'Pending' ?>
                    </span>
                    <span style="font-size:.78rem;color:var(--brand-muted);">
                        <?= e(date('M j, Y', strtotime($inq['created_at']))) ?>
                    </span>
                </div>
            </div>

            <!-- Message -->
            <p style="font-size:.9rem;color:#2a3e2d;line-height:1.75;white-space:pre-wrap;
                      word-break:break-word;margin-bottom:0;">
                <?= e($inq['message']) ?>
            </p>

            <!-- Admin reply -->
            <?php if (!empty($inq['admin_reply'])): ?>
            <div class="mt-3 p-3 rounded-2"
                 style="background:#f0f9f3;border-left:4px solid var(--brand-green);">
                <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;
                            letter-spacing:.06em;color:var(--brand-green);margin-bottom:.35rem;">
                    <i class="bi bi-shield-check me-1"></i>Admin Reply
                </div>
                <p style="font-size:.9rem;line-height:1.75;margin:0;white-space:pre-wrap;
                          word-break:break-word;">
                    <?= e($inq['admin_reply']) ?>
                </p>
            </div>
            <?php elseif (!$is_resolved): ?>
            <p class="mt-2 mb-0" style="font-size:.8rem;color:var(--brand-muted);">
                <i class="bi bi-clock me-1"></i>Awaiting admin response.
            </p>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
</section>
</main>

<?php require_once 'includes/footer.php'; ?>