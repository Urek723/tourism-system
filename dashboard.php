<?php
/**
 * dashboard.php
 * Shows the user's personalized destination feed.
 * Renders the onboarding modal on first login if no preferences are set.
 */

require_once 'db.php';
require_once 'functions.php';

start_secure_session();
require_login();

$page_title = 'My Dashboard';
$user_id    = (int) $_SESSION['user_id'];

// Read and clear the onboarding flag set by login.php
$show_onboarding = !empty($_SESSION['show_onboarding']);
unset($_SESSION['show_onboarding']);

// ── Fetch user's preferences ──────────────────────────────────────────────
$user_prefs = [];
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT category FROM user_preferences WHERE user_id = ? ORDER BY category ASC'
    );
    $stmt->execute([$user_id]);
    $user_prefs = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('[DASHBOARD — PREFS] ' . $e->getMessage());
}

// ── Fetch all categories for onboarding modal ─────────────────────────────
$all_categories = [];
try {
    $stmt = $db->prepare(
        'SELECT DISTINCT category FROM locations WHERE is_active = 1 ORDER BY category ASC'
    );
    $stmt->execute();
    $all_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('[DASHBOARD — CATS] ' . $e->getMessage());
}

// ── Fetch locations (filtered by preferences if set, otherwise all) ────────
$locations = [];
try {
    if (!empty($user_prefs)) {
        $placeholders = implode(',', array_fill(0, count($user_prefs), '?'));
        $stmt = $db->prepare(
            "SELECT id, title, description, cost, category, created_at
             FROM   locations
             WHERE  is_active = 1
               AND  category IN ($placeholders)
             ORDER  BY created_at DESC
             LIMIT  6"
        );
        $stmt->execute($user_prefs);
    } else {
        $stmt = $db->prepare(
            'SELECT id, title, description, cost, category, created_at
             FROM   locations
             WHERE  is_active = 1
             ORDER  BY created_at DESC
             LIMIT  6'
        );
        $stmt->execute();
    }
    $locations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[DASHBOARD — LOCATIONS] ' . $e->getMessage());
}

// ── Total location count ──────────────────────────────────────────────────
$total_locations = 0;
try {
    $stmt = $db->prepare('SELECT COUNT(*) FROM locations WHERE is_active = 1');
    $stmt->execute();
    $total_locations = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    error_log('[DASHBOARD — COUNT] ' . $e->getMessage());
}

require_once 'includes/header.php';
?>

<main class="py-5">
    <div class="container">

        <!-- Page header -->
        <div class="d-flex align-items-start justify-content-between mb-4 flex-wrap gap-3">
            <div>
                <p class="section-eyebrow mb-1">Dashboard</p>
                <h1 style="font-size:1.9rem;margin:0;">
                    Welcome back, <?= e($_SESSION['username']) ?>!
                </h1>
                <p style="color:var(--brand-muted);margin-top:.25rem;font-size:.9rem;">
                    <i class="bi bi-clock me-1"></i>
                    Signed in
                    <?= date('F j, Y \a\t g:i A', $_SESSION['logged_in_at'] ?? time()) ?>
                </p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="edit_account.php" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil-square me-2"></i>Edit Account
                </a>
                <?php if (is_admin()): ?>
                <a href="admin/admin_panel.php" class="btn btn-brand">
                    <i class="bi bi-shield-lock me-2"></i>Admin Panel
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?= flash_alert('success') ?>
        <?= flash_alert('error') ?>

        <!-- Stats row -->
        <div class="row g-3 mb-5">
            <div class="col-6 col-md-3">
                <div class="p-3 text-center rounded-3"
                     style="background:#fff;box-shadow:0 1px 8px rgba(0,0,0,.06);">
                    <i class="bi bi-geo-alt-fill fs-3 text-brand d-block mb-1"></i>
                    <div style="font-size:1.6rem;font-weight:800;font-family:var(--font-display);">
                        <?= $total_locations ?>
                    </div>
                    <div style="font-size:.75rem;text-transform:uppercase;
                                letter-spacing:.06em;color:var(--brand-muted);font-weight:600;">
                        Destinations
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 text-center rounded-3"
                     style="background:#fff;box-shadow:0 1px 8px rgba(0,0,0,.06);">
                    <i class="bi bi-heart-fill fs-3 text-brand d-block mb-1"></i>
                    <div style="font-size:1.6rem;font-weight:800;font-family:var(--font-display);">
                        <?= count($user_prefs) ?>
                    </div>
                    <div style="font-size:.75rem;text-transform:uppercase;
                                letter-spacing:.06em;color:var(--brand-muted);font-weight:600;">
                        Interests Set
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="p-3 text-center rounded-3"
                     style="background:#fff;box-shadow:0 1px 8px rgba(0,0,0,.06);">
                    <i class="bi bi-person-badge-fill fs-3 text-brand d-block mb-1"></i>
                    <div style="font-size:1rem;font-weight:700;font-family:var(--font-display);
                                text-transform:capitalize;">
                        <?= e($_SESSION['user_role'] ?? 'user') ?>
                    </div>
                    <div style="font-size:.75rem;text-transform:uppercase;
                                letter-spacing:.06em;color:var(--brand-muted);font-weight:600;">
                        Account Type
                    </div>
                </div>
            </div>
        </div>

        <!-- User interests strip -->
        <?php if (!empty($user_prefs)): ?>
        <div class="mb-4 p-3 rounded-3"
             style="background:#fff;box-shadow:0 1px 8px rgba(0,0,0,.06);">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <span style="font-size:.8rem;font-weight:700;text-transform:uppercase;
                                 letter-spacing:.06em;color:var(--brand-muted);">
                        Your Interests
                    </span>
                    <div class="d-flex flex-wrap gap-2 mt-2">
                        <?php foreach ($user_prefs as $pref): ?>
                        <span class="badge-category">
                            <i class="bi bi-tag-fill me-1"></i><?= e($pref) ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <a href="edit_account.php#preferences"
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-pencil me-1"></i>Edit Interests
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="mb-4 p-3 rounded-3"
             style="background:#fff8e7;border:1px dashed #e0c060;
                    box-shadow:0 1px 8px rgba(0,0,0,.04);">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <span style="font-weight:600;color:#7a5c00;">
                        <i class="bi bi-lightbulb me-2"></i>Personalise your feed
                    </span>
                    <p style="font-size:.875rem;color:#8a6a10;margin:.25rem 0 0;">
                        Set your interests to see destinations you'll love first.
                    </p>
                </div>
                <button class="btn btn-gold btn-sm" data-bs-toggle="modal"
                        data-bs-target="#onboardingModal">
                    Set Interests
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Destinations section -->
        <div class="d-flex align-items-center justify-content-between mb-1 flex-wrap gap-2">
            <h2 style="font-size:1.5rem;margin:0;">
                <?= !empty($user_prefs) ? 'Recommended for You' : 'Explore Destinations' ?>
            </h2>
            <a href="locations.php" class="btn btn-outline-secondary btn-sm">
                View All <?= $total_locations ?> Destinations
            </a>
        </div>
        <hr class="divider-brand">

        <?php if (empty($locations)): ?>
            <div class="alert alert-info">
                <?= !empty($user_prefs)
                    ? 'No destinations match your current interests. '
                      . '<a href="edit_account.php" class="alert-link">Update your interests</a> '
                      . 'or <a href="locations.php" class="alert-link">browse all destinations</a>.'
                    : 'No destinations available yet.' ?>
            </div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($locations as $loc): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card location-card">
                    <div class="card-body">
                        <div class="d-flex gap-2 mb-2">
                            <span class="badge-category"><?= e($loc['category']) ?></span>
                            <span class="badge-cost"><?= e($loc['cost']) ?></span>
                        </div>
                        <h5 class="card-title"><?= e($loc['title']) ?></h5>
                        <p class="card-text">
                            <?= e(mb_substr($loc['description'], 0, 100)) ?>…
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-0 px-3 pb-3">
                        <a href="location.php?id=<?= (int)$loc['id'] ?>"
                           class="btn btn-brand btn-sm w-100">
                            View Details <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- ── Onboarding Preferences Modal ──────────────────────────── -->
<?php if (!empty($all_categories)): ?>
<div class="modal fade" id="onboardingModal"
     tabindex="-1"
     data-bs-backdrop="<?= $show_onboarding ? 'static' : 'true' ?>"
     data-bs-keyboard="<?= $show_onboarding ? 'false' : 'true' ?>">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="font-family:var(--font-display);">
                    <i class="bi bi-heart text-brand me-2"></i>What interests you?
                </h5>
                <?php if (!$show_onboarding): ?>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                <?php endif; ?>
            </div>
            <div class="modal-body pt-2">
                <p style="font-size:.9rem;color:var(--brand-muted);">
                    Select the types of destinations you enjoy. We'll personalise your feed.
                </p>
                <div id="pref-btn-group" class="d-flex flex-wrap gap-2">
                    <?php foreach ($all_categories as $cat): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-secondary pref-option"
                            data-cat="<?= eAttr($cat) ?>">
                        <?= e($cat) ?>
                    </button>
                    <?php endforeach; ?>
                </div>
                <p class="mt-3 mb-0" style="font-size:.8rem;color:var(--brand-muted);">
                    You can update these anytime from Edit Account.
                </p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <?php if ($show_onboarding): ?>
                <button type="button" class="btn btn-outline-secondary btn-sm"
                        id="skipOnboarding">
                    Skip for now
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-brand btn-sm px-4"
                        id="savePrefsBtn">
                    Save Interests
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.pref-option.selected {
    background: var(--brand-green);
    border-color: var(--brand-green);
    color: #fff;
}
</style>

<script>
(function () {
    var csrf     = <?= json_encode(csrf_token()) ?>;
    var autoShow = <?= $show_onboarding ? 'true' : 'false' ?>;
    var modal    = new bootstrap.Modal(document.getElementById('onboardingModal'));

    if (autoShow) {
        modal.show();
    }

    // Toggle selection on pref buttons
    document.querySelectorAll('.pref-option').forEach(function (btn) {
        btn.addEventListener('click', function () {
            btn.classList.toggle('selected');
        });
    });

    // Skip button — just close without saving
    var skipBtn = document.getElementById('skipOnboarding');
    if (skipBtn) {
        skipBtn.addEventListener('click', function () {
            modal.hide();
        });
    }

    // Save button
    document.getElementById('savePrefsBtn').addEventListener('click', function () {
        var selected = [];
        document.querySelectorAll('.pref-option.selected').forEach(function (btn) {
            selected.push(btn.dataset.cat);
        });

        fetch('save_preferences.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ csrf_token: csrf, categories: selected })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                modal.hide();
                // Reload so the feed updates with the new preferences
                window.location.reload();
            } else {
                alert('Could not save preferences. Please try again.');
            }
        })
        .catch(function () {
            alert('A network error occurred. Please try again.');
        });
    });
}());
</script>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>