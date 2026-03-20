<?php
/**
 * trip_planner.php
 * Allows users to add destinations to a date-based trip plan and view their itinerary.
 *
 * Security: require_login, CSRF, clean_int, clean_string (date),
 *           user_id from session only, PDO prepared statements.
 */

require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';

start_secure_session();
require_login();

$user_id = (int)$_SESSION['user_id'];
$page_title = 'Trip Planner';
$errors = [];

// Handle POST: add trip entry or delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Delete
    if (isset($_POST['delete_trip'])) {
        $trip_id = clean_int($_POST['trip_id'] ?? 0);
        if ($trip_id > 0) {
            try {
                $db   = get_db();
                // Ownership check: WHERE user_id = ? ensures users can only delete their own
                $stmt = $db->prepare('DELETE FROM trip_plans WHERE id = ? AND user_id = ?');
                $stmt->execute([$trip_id, $user_id]);
                set_flash('success', 'Trip entry removed.');
            } catch (PDOException $e) {
                error_log('[TRIP DELETE] ' . $e->getMessage());
                set_flash('error', 'Could not remove entry.');
            }
        }
        redirect('trip_planner.php');
    }

    // Add
    $location_id = clean_int($_POST['location_id'] ?? 0);
    $trip_date   = clean_string($_POST['trip_date'] ?? '', 10);
    $notes       = clean_string($_POST['notes'] ?? '', 500);

    // Validate date format YYYY-MM-DD
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $trip_date) ||
        !checkdate(
            (int)substr($trip_date, 5, 2),
            (int)substr($trip_date, 8, 2),
            (int)substr($trip_date, 0, 4)
        )) {
        $errors[] = 'Please select a valid date.';
    }

    if ($location_id === 0) $errors[] = 'Please select a destination.';

    if (empty($errors)) {
        try {
            $db = get_db();

            // Verify location exists
            $chk = $db->prepare('SELECT id FROM locations WHERE id = ? AND is_active = 1');
            $chk->execute([$location_id]);
            if (!$chk->fetch()) {
                $errors[] = 'Invalid destination.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO trip_plans (user_id, location_id, trip_date, notes)
                     VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$user_id, $location_id, $trip_date, $notes]);
                log_activity($user_id, 'trip_added');
                set_flash('success', 'Destination added to your trip!');
                redirect('trip_planner.php');
            }
        } catch (PDOException $e) {
            error_log('[TRIP ADD] ' . $e->getMessage());
            $errors[] = 'Could not save trip entry. Please try again.';
        }
    }
}

// Fetch all active locations for the dropdown
$all_locs = [];
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT id, title, category FROM locations WHERE is_active = 1 ORDER BY title ASC'
    );
    $stmt->execute();
    $all_locs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[TRIP LOCS] ' . $e->getMessage());
}

// Fetch user's trip plans ordered by date
$trips = [];
try {
    $stmt = $db->prepare(
        'SELECT tp.id, tp.trip_date, tp.notes, l.id AS loc_id,
                l.title, l.category, l.cost, l.image_url
         FROM   trip_plans tp
         JOIN   locations   l ON l.id = tp.location_id
         WHERE  tp.user_id = ?
         ORDER  BY tp.trip_date ASC'
    );
    $stmt->execute([$user_id]);
    $trips = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[TRIP LIST] ' . $e->getMessage());
}

require_once 'includes/header.php';
?>

<main>
<section style="background:var(--brand-dark);padding:3.5rem 0 2.5rem;">
    <div class="container">
        <p class="section-eyebrow" style="color:var(--brand-gold);">My Account</p>
        <h1 style="color:#fff;font-size:2.5rem;margin-bottom:.5rem;">Trip Planner</h1>
        <p style="color:rgba(255,255,255,.6);margin:0;">
            Plan your itinerary — <?= count($trips) ?> destination<?= count($trips) !== 1 ? 's' : '' ?> scheduled.
        </p>
    </div>
</section>

<section class="py-5">
<div class="container">
    <div class="row g-5">

        <!-- Add form -->
        <div class="col-lg-4">
            <div class="auth-card p-4">
                <h5 class="mb-3"><i class="bi bi-plus-circle text-brand me-2"></i>Add to Trip</h5>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="trip_planner.php" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label">Destination</label>
                        <select name="location_id" class="form-select" required>
                            <option value="">-- Select --</option>
                            <?php foreach ($all_locs as $loc): ?>
                            <option value="<?= (int)$loc['id'] ?>"
                                    <?= clean_int($_POST['location_id'] ?? 0) === (int)$loc['id'] ? 'selected' : '' ?>>
                                <?= e($loc['title']) ?> (<?= e($loc['category']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Trip Date</label>
                        <input type="date" name="trip_date" class="form-control"
                               min="<?= date('Y-m-d') ?>"
                               value="<?= eAttr($_POST['trip_date'] ?? '') ?>"
                               required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Notes <span class="text-muted">(optional)</span></label>
                        <textarea name="notes" class="form-control" rows="2"
                                  maxlength="500"
                                  placeholder="What to bring, reminders…"><?= e($_POST['notes'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-brand w-100">
                        <i class="bi bi-plus me-2"></i>Add Destination
                    </button>
                </form>
            </div>
        </div>

        <!-- Itinerary -->
        <div class="col-lg-8">
            <?= flash_alert('success') ?>
            <?= flash_alert('error') ?>

            <?php if (empty($trips)): ?>
            <div class="text-center py-5">
                <i class="bi bi-map text-brand" style="font-size:3rem;opacity:.4;"></i>
                <h4 class="mt-3">Your itinerary is empty</h4>
                <p style="color:var(--brand-muted);">Add destinations using the form to start planning.</p>
            </div>
            <?php else: ?>
            <h5 class="mb-3">Your Itinerary</h5>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($trips as $t): ?>
                <div class="d-flex gap-3 p-3 rounded-3"
                     style="background:#fff;box-shadow:0 1px 8px rgba(0,0,0,.06);">
                    <!-- Date badge -->
                    <div style="width:56px;flex-shrink:0;text-align:center;">
                        <div style="background:var(--brand-green);color:#fff;border-radius:8px;padding:.4rem .2rem;">
                            <div style="font-size:.65rem;text-transform:uppercase;letter-spacing:.05em;">
                                <?= e(date('M', strtotime($t['trip_date']))) ?>
                            </div>
                            <div style="font-size:1.4rem;font-weight:900;line-height:1;font-family:var(--font-display);">
                                <?= e(date('d', strtotime($t['trip_date']))) ?>
                            </div>
                            <div style="font-size:.65rem;">
                                <?= e(date('Y', strtotime($t['trip_date']))) ?>
                            </div>
                        </div>
                    </div>
                    <!-- Details -->
                    <div class="flex-grow-1 min-width-0">
                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <span class="fw-bold"><?= e($t['title']) ?></span>
                            <span class="badge-category"><?= e($t['category']) ?></span>
                            <span class="badge-cost"><?= e($t['cost']) ?></span>
                        </div>
                        <?php if (!empty($t['notes'])): ?>
                        <p class="mb-0" style="font-size:.85rem;color:var(--brand-muted);">
                            <i class="bi bi-sticky me-1"></i><?= e($t['notes']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <!-- Actions -->
                    <div class="d-flex gap-1 align-items-start">
                        <a href="location.php?id=<?= (int)$t['loc_id'] ?>"
                           class="btn btn-sm btn-outline-secondary"
                           title="View destination">
                            <i class="bi bi-eye"></i>
                        </a>
                        <form method="POST" action="trip_planner.php"
                              onsubmit="return confirm('Remove this trip entry?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delete_trip" value="1">
                            <input type="hidden" name="trip_id" value="<?= (int)$t['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    title="Remove">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>
</section>
</main>

<?php require_once 'includes/footer.php'; ?>