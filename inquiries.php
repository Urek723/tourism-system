<?php
require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';

start_secure_session();
require_login();

$page_title = 'Submit Inquiry';
$errors     = [];
$success    = false;

// Fetch active locations for optional dropdown
$locations = [];
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT id, title FROM locations WHERE is_active = 1 ORDER BY title ASC'
    );
    $stmt->execute();
    $locations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[INQUIRIES — FETCH LOCS] ' . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_csrf();

    $user_id     = (int) $_SESSION['user_id'];
    $subject     = clean_string($_POST['subject']     ?? '', 255);
    $message     = clean_string($_POST['message']     ?? '', 2000);
    $location_id = clean_int($_POST['location_id']    ?? 0);

    // Pre-fill name/email from session user record
    try {
        $uStmt = $db->prepare('SELECT username, email FROM users WHERE id = ? LIMIT 1');
        $uStmt->execute([$user_id]);
        $u = $uStmt->fetch();
        $name  = $u['username'] ?? '';
        $email = $u['email']    ?? '';
    } catch (PDOException $e) {
        error_log('[INQUIRIES — FETCH USER] ' . $e->getMessage());
        $name  = '';
        $email = '';
    }

    if (empty($subject)) {
        $errors[] = 'Subject is required.';
    } elseif (mb_strlen($subject) < 3) {
        $errors[] = 'Subject must be at least 3 characters.';
    }

    if (empty($message)) {
        $errors[] = 'Message is required.';
    } elseif (mb_strlen($message) < 10) {
        $errors[] = 'Message must be at least 10 characters.';
    }

    // Validate location_id only if provided
    $final_location_id = null;
    if ($location_id > 0) {
        try {
            $chk = $db->prepare('SELECT id FROM locations WHERE id = ? AND is_active = 1 LIMIT 1');
            $chk->execute([$location_id]);
            if ($chk->fetch()) {
                $final_location_id = $location_id;
            } else {
                $errors[] = 'Selected destination is invalid.';
            }
        } catch (PDOException $e) {
            error_log('[INQUIRIES — LOC CHECK] ' . $e->getMessage());
            $errors[] = 'Could not validate destination. Please try again.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = $db->prepare(
                'INSERT INTO inquiries (user_id, location_id, name, email, subject, message)
                 VALUES (:user_id, :location_id, :name, :email, :subject, :message)'
            );
            $stmt->execute([
                ':user_id'     => $user_id,
                ':location_id' => $final_location_id,
                ':name'        => $name,
                ':email'       => $email,
                ':subject'     => $subject,
                ':message'     => $message,
            ]);
            log_activity($user_id, 'user_submitted_inquiry');
            set_flash('success', 'Your inquiry has been submitted. We will respond shortly.');
            redirect('my_inquiries.php');
        } catch (PDOException $e) {
            error_log('[INQUIRIES — INSERT] ' . $e->getMessage());
            $errors[] = 'Could not submit your inquiry. Please try again.';
        }
    }
}

require_once 'includes/header.php';
?>

<main class="py-5">
<div class="container">
<div class="row justify-content-center">
<div class="col-lg-7 col-xl-6">

    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb" style="font-size:.85rem;">
            <li class="breadcrumb-item">
                <a href="dashboard.php" class="text-brand">Dashboard</a>
            </li>
            <li class="breadcrumb-item">
                <a href="my_inquiries.php" class="text-brand">My Inquiries</a>
            </li>
            <li class="breadcrumb-item active">Submit Inquiry</li>
        </ol>
    </nav>

    <p class="section-eyebrow mb-1">Support</p>
    <h1 style="font-size:2rem;" class="mb-4">Submit an Inquiry</h1>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle me-2"></i>
        <strong>Please fix the following:</strong>
        <ul class="mb-0 mt-2 ps-3">
            <?php foreach ($errors as $err): ?>
                <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="auth-card p-4 p-md-5">
        <form method="POST" action="inquiries.php" novalidate>
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label">Related Destination
                    <span class="text-muted fw-normal">(optional)</span>
                </label>
                <select name="location_id" class="form-select">
                    <option value="">-- None / General inquiry --</option>
                    <?php foreach ($locations as $loc): ?>
                    <option value="<?= (int)$loc['id'] ?>"
                            <?= clean_int($_POST['location_id'] ?? 0) === (int)$loc['id'] ? 'selected' : '' ?>>
                        <?= e($loc['title']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Subject *</label>
                <input type="text" name="subject" class="form-control"
                       maxlength="255"
                       placeholder="e.g. Booking question about Lake Sebu"
                       value="<?= eAttr($_POST['subject'] ?? '') ?>"
                       required>
                <div class="form-text">3–255 characters.</div>
            </div>

            <div class="mb-4">
                <label class="form-label">Message *</label>
                <textarea name="message" id="inq-message" class="form-control"
                          rows="6" maxlength="2000"
                          placeholder="Describe your inquiry in detail…"
                          required><?= e($_POST['message'] ?? '') ?></textarea>
                <div class="d-flex justify-content-between form-text">
                    <span>Minimum 10 characters.</span>
                    <span id="inq-char-count">0 / 2000</span>
                </div>
            </div>

            <div class="d-flex gap-3">
                <button type="submit" class="btn btn-brand px-4">
                    <i class="bi bi-send me-2"></i>Submit Inquiry
                </button>
                <a href="my_inquiries.php" class="btn btn-outline-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>

</div>
</div>
</div>
</main>

<script>
(function () {
    var ta  = document.getElementById('inq-message');
    var cnt = document.getElementById('inq-char-count');
    if (!ta || !cnt) return;
    ta.addEventListener('input', function () {
        var n = ta.value.length;
        cnt.textContent = n + ' / 2000';
        cnt.style.color = n > 1800 ? '#c0392b' : '';
    });
}());
</script>

<?php require_once 'includes/footer.php'; ?>