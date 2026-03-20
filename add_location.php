<?php

require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';

start_secure_session();

// SECURITY: Only admins can add locations
require_admin();

$page_title = 'Add Location';
$errors     = [];
$old        = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── 1. Verify CSRF ─────────────────────────────────────────
    require_csrf();

    // ── 2. Sanitise & validate ─────────────────────────────────
    $old['title']       = clean_string($_POST['title']       ?? '', 300);
    $old['description'] = clean_string($_POST['description'] ?? '', 5000);
    $old['cost']        = clean_string($_POST['cost']        ?? '', 100);
    $old['category']    = clean_string($_POST['category']    ?? '', 100);
    $old['image_url']   = trim($_POST['image_url'] ?? '');

    if (empty($old['title']))
        $errors[] = 'Title is required.';
    elseif (strlen($old['title']) < 3)
        $errors[] = 'Title must be at least 3 characters.';

    if (empty($old['description']))
        $errors[] = 'Description is required.';
    elseif (strlen($old['description']) < 20)
        $errors[] = 'Description must be at least 20 characters.';

    if (empty($old['cost']))
        $errors[] = 'Cost/entry information is required.';

    if (empty($old['category']))
        $errors[] = 'Category is required.';

    // Validate image URL if provided
    if (!empty($old['image_url'])) {
        $validated_url = filter_var($old['image_url'], FILTER_VALIDATE_URL);
        if ($validated_url === false) {
            $errors[] = 'Image URL must be a valid URL (e.g. https://...).';
            $old['image_url'] = '';
        } else {
            // Only allow http/https URLs
            $scheme = parse_url($validated_url, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'], true)) {
                $errors[] = 'Image URL must use http or https.';
                $old['image_url'] = '';
            } else {
                $old['image_url'] = $validated_url;
            }
        }
    }

    // ── 3. Insert with prepared statement ──────────────────────
    if (empty($errors)) {
        try {
            $db   = get_db();
            $stmt = $db->prepare(
                'INSERT INTO locations (title, description, cost, category, image_url)
                 VALUES (:title, :description, :cost, :category, :image_url)'
            );
            $stmt->execute([
                ':title'       => $old['title'],
                ':description' => $old['description'],
                ':cost'        => $old['cost'],
                ':category'    => $old['category'],
                ':image_url'   => !empty($old['image_url']) ? $old['image_url'] : null,
            ]);

            log_activity((int)$_SESSION['user_id'], 'admin_location_added');

            // PRG Pattern: redirect after POST to prevent duplicate submissions
            set_flash('success', 'Location "' . $old['title'] . '" added successfully!');
            redirect('admin/admin_panel.php');

        } catch (PDOException $e) {
            error_log('[ADD LOCATION ERROR] ' . $e->getMessage());
            $errors[] = 'Failed to save the location. Please try again.';
        }
    }
}

$predefined_categories = ['Nature', 'Adventure', 'Culture', 'Agri-Tourism', 'Accommodation', 'Heritage'];

require_once 'includes/header.php';
?>

<main class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <!-- Breadcrumb -->
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb" style="font-size:.85rem;">
                        <li class="breadcrumb-item">
                            <a href="admin/admin_panel.php" class="text-brand">Admin</a>
                        </li>
                        <li class="breadcrumb-item active">Add Location</li>
                    </ol>
                </nav>

                <p class="section-eyebrow">Admin Panel</p>
                <h1 style="font-size:2rem;" class="mb-4">Add New Destination</h1>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Please fix the following errors:</strong>
                    <ul class="mb-0 mt-2 ps-3">
                        <?php foreach ($errors as $err): ?>
                            <li><?= e($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <div class="auth-card p-4 p-md-5">
                    <form method="POST" action="add_location.php" novalidate>
                        <?= csrf_field() ?>

                        <!-- Title -->
                        <div class="mb-3">
                            <label for="title" class="form-label">
                                Destination Title *
                            </label>
                            <input type="text" id="title" name="title"
                                   class="form-control"
                                   value="<?= eAttr($old['title'] ?? '') ?>"
                                   maxlength="300"
                                   placeholder="e.g. Lake Sebu"
                                   required>
                        </div>

                        <!-- Description -->
                        <div class="mb-3">
                            <label for="description" class="form-label">
                                Description *
                            </label>
                            <textarea id="description" name="description"
                                      class="form-control"
                                      rows="6"
                                      maxlength="5000"
                                      placeholder="Provide a detailed description of the destination…"
                                      required><?= e($old['description'] ?? '') ?></textarea>
                            <div class="form-text">Minimum 20 characters.</div>
                        </div>

                        <!-- Cost and Category row -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="cost" class="form-label">Entry Cost *</label>
                                <input type="text" id="cost" name="cost"
                                       class="form-control"
                                       value="<?= eAttr($old['cost'] ?? '') ?>"
                                       maxlength="100"
                                       placeholder="e.g. Free entry or ₱200 – ₱500"
                                       required>
                            </div>
                            <div class="col-md-6">
                                <label for="category" class="form-label">Category *</label>
                                <select id="category" name="category"
                                        class="form-select" required>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($predefined_categories as $cat): ?>
                                    <option value="<?= eAttr($cat) ?>"
                                            <?= ($old['category'] ?? '') === $cat ? 'selected' : '' ?>>
                                        <?= e($cat) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Image URL -->
                        <div class="mb-4">
                            <label for="image_url" class="form-label">
                                Image URL
                                <span class="text-muted fw-normal">(optional)</span>
                            </label>
                            <input type="url" id="image_url" name="image_url"
                                   class="form-control"
                                   value="<?= eAttr($old['image_url'] ?? '') ?>"
                                   maxlength="500"
                                   placeholder="https://example.com/image.jpg">
                            <div class="form-text">
                                Paste a full https:// URL to an image. Leave blank if not available.
                            </div>
                        </div>

                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-brand px-4">
                                <i class="bi bi-plus-circle me-2"></i>Add Location
                            </button>
                            <a href="admin/admin_panel.php"
                               class="btn btn-outline-secondary px-4">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>

            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>