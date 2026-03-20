<?php
/**
 * bookings.php
 * Simulated booking system — no payment, status-based only.
 *
 * Security: require_login, CSRF, clean_int, date validation,
 *           user_id from session, ownership enforced on cancel.
 */

require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';

start_secure_session();
require_login();

$user_id    = (int)$_SESSION['user_id'];
$page_title = 'My Bookings';
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    // Cancel booking
    if (isset($_POST['cancel_booking'])) {
        $book_id = clean_int($_POST['booking_id'] ?? 0);
        if ($book_id > 0) {
            try {
                $db   = get_db();
                // Ownership check via AND user_id = ?
                $stmt = $db->prepare(
                    "UPDATE bookings SET status = 'cancelled'
                     WHERE id = ? AND user_id = ? AND status = 'pending'"
                );
                $stmt->execute([$book_id, $user_id]);
                log_activity($user_id, 'booking_cancelled');
                set_flash('success', 'Booking cancelled.');
            } catch (PDOException $e) {
                error_log('[BOOKING CANCEL] ' . $e->getMessage());
                set_flash('error', 'Could not cancel booking.');
            }
        }
        redirect('bookings.php');
    }

    // New booking
    $location_id  = clean_int($_POST['location_id'] ?? 0);
    $booking_date = clean_string($_POST['date'] ?? '', 10);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date) ||
        !checkdate(
            (int)substr($booking_date, 5, 2),
            (int)substr($booking_date, 8, 2),
            (int)substr($booking_date, 0, 4)
        )) {
        $errors[] = 'Please select a valid date.';
    }

    if ($location_id === 0) $errors[] = 'Please select a destination.';

    if (empty($errors)) {
        try {
            $db = get_db();

            $chk = $db->prepare('SELECT id FROM locations WHERE id = ? AND is_active = 1');
            $chk->execute([$location_id]);
            if (!$chk->fetch()) {
                $errors[] = 'Invalid destination.';
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO bookings (user_id, location_id, date) VALUES (?, ?, ?)'
                );
                $stmt->execute([$user_id, $location_id, $booking_date]);
                log_activity($user_id, 'booking_created');
                set_flash('success', 'Booking submitted! Status: Pending.');
                redirect('bookings.php');
            }
        } catch (PDOException $e) {
            error_log('[BOOKING CREATE] ' . $e->getMessage());
            $errors[] = 'Could not create booking. Please try again.';
        }
    }
}

// Fetch locations for dropdown
$all_locs = [];
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT id, title, category FROM locations WHERE is_active = 1 ORDER BY title ASC'
    );
    $stmt->execute();
    $all_locs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[BOOKING LOCS] ' . $e->getMessage());
}

// Fetch user's bookings
$bookings = [];
try {
    $stmt = $db->prepare(
        'SELECT b.id, b.date, b.status, b.created_at,
                l.id AS loc_id, l.title, l.category, l.cost
         FROM   bookings  b
         JOIN   locations l ON l.id = b.location_id
         WHERE  b.user_id = ?
         ORDER  BY b.date DESC'
    );
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[BOOKING LIST] ' . $e->getMessage());
}

require_once 'includes/header.php';
?>

<style>
.status-badge {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    padding: .25em .7em;
    border-radius: 999px;
}
.status-pending   { background:#fff3cd;color:#856404; }
.status-confirmed { background:#d1e7dd;color:#0a3622; }
.status-cancelled { background:#f8d7da;color:#58151c; }
</style>

<main>
<section style="background:var(--brand-dark);padding:3.5rem 0 2.5rem;">
    <div class="container">
        <p class="section-eyebrow" style="color:var(--brand-gold);">My Account</p>
        <h1 style="color:#fff;font-size:2.5rem;margin-bottom:.5rem;">My Bookings</h1>
        <p style="color:rgba(255,255,255,.6);margin:0;">
            Simulated booking — no payment required.
        </p>
    </div>
</section>

<section class="py-5">
<div class="container">
<div class="row g-5">

    <!-- Booking form -->
    <div class="col-lg-4">
        <div class="auth-card p-4">
            <h5 class="mb-3"><i class="bi bi-calendar-plus text-brand me-2"></i>New Booking</h5>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="bookings.php" novalidate>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label">Destination</label>
                    <select name="location_id" class="form-select" required>
                        <option value="">-- Select --</option>
                        <?php foreach ($all_locs as $loc): ?>
                        <option value="<?= (int)$loc['id'] ?>"
                                <?= clean_int($_POST['location_id'] ?? 0) === (int)$loc['id'] ? 'selected' : '' ?>>
                            <?= e($loc['title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label">Visit Date</label>
                    <input type="date" name="date" class="form-control"
                           min="<?= date('Y-m-d') ?>"
                           value="<?= eAttr($_POST['date'] ?? '') ?>"
                           required>
                </div>

                <button type="submit" class="btn btn-brand w-100">
                    <i class="bi bi-calendar-check me-2"></i>Book Now
                </button>
            </form>
        </div>
    </div>

    <!-- Bookings list -->
    <div class="col-lg-8">
        <?= flash_alert('success') ?>
        <?= flash_alert('error') ?>

        <?php if (empty($bookings)): ?>
        <div class="text-center py-5">
            <i class="bi bi-calendar2-x text-brand" style="font-size:3rem;opacity:.4;"></i>
            <h4 class="mt-3">No bookings yet</h4>
            <p style="color:var(--brand-muted);">Use the form to book a destination visit.</p>
        </div>
        <?php else: ?>
        <h5 class="mb-3">Booking History</h5>
        <div class="d-flex flex-column gap-3">
            <?php foreach ($bookings as $b): ?>
            <div class="p-3 rounded-3 d-flex align-items-center gap-3 flex-wrap"
                 style="background:#fff;box-shadow:0 1px 8px rgba(0,0,0,.06);">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                        <span class="fw-bold"><?= e($b['title']) ?></span>
                        <span class="badge-category"><?= e($b['category']) ?></span>
                        <span class="status-badge status-<?= e($b['status']) ?>">
                            <?= e(ucfirst($b['status'])) ?>
                        </span>
                    </div>
                    <div style="font-size:.82rem;color:var(--brand-muted);">
                        <i class="bi bi-calendar3 me-1"></i>
                        <?= e(date('F j, Y', strtotime($b['date']))) ?>
                        &nbsp;·&nbsp;Booked <?= e(date('M j, Y', strtotime($b['created_at']))) ?>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="location.php?id=<?= (int)$b['loc_id'] ?>"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-eye"></i>
                    </a>
                    <?php if ($b['status'] === 'pending'): ?>
                    <form method="POST" action="bookings.php"
                          onsubmit="return confirm('Cancel this booking?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="cancel_booking" value="1">
                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                title="Cancel booking">
                            <i class="bi bi-x-circle"></i>
                        </button>
                    </form>
                    <?php endif; ?>
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