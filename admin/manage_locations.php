<?php
/**
 * admin/manage_locations.php — Location Management
 *
 * Security: require_admin + admin_session, CSRF, clean_int/clean_string,
 *           filter_var for URL, PDO prepared statements, e() on all output.
 */

require_once '../db.php';
require_once '../functions.php';
require_once '../activity_logger.php';

start_secure_session();
require_admin();
if (empty($_SESSION['admin_session'])) { destroy_session(); redirect('admin_login.php'); }

$page_title = 'Manage Locations';
$admin_id   = (int)$_SESSION['user_id'];

// ── POST: delete ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_location'])) {
    require_csrf();
    $id = clean_int($_POST['location_id'] ?? 0);
    if ($id > 0) {
        try {
            $db = get_db();
            $db->prepare('DELETE FROM locations WHERE id = ?')->execute([$id]);
            log_activity($admin_id, 'admin_location_deleted');
            set_flash('success', 'Location deleted.');
        } catch (PDOException $e) {
            error_log('[ADMIN DEL LOC] ' . $e->getMessage());
            set_flash('error', 'Could not delete location.');
        }
    }
    redirect('admin/manage_locations.php');
}

// ── POST: toggle active ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_active'])) {
    require_csrf();
    $id = clean_int($_POST['location_id'] ?? 0);
    if ($id > 0) {
        try {
            $db = get_db();
            $db->prepare('UPDATE locations SET is_active = 1 - is_active WHERE id = ?')
               ->execute([$id]);
            set_flash('success', 'Status updated.');
        } catch (PDOException $e) {
            error_log('[ADMIN TOGGLE LOC] ' . $e->getMessage());
            set_flash('error', 'Could not update status.');
        }
    }
    redirect('admin/manage_locations.php');
}

// ── POST: add location ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_location'])) {
    require_csrf();

    $title       = clean_string($_POST['title']       ?? '', 300);
    $description = clean_string($_POST['description'] ?? '', 5000);
    $cost        = clean_string($_POST['cost']        ?? '', 100);
    $category    = clean_string($_POST['category']    ?? '', 100);
    $image_url   = trim($_POST['image_url'] ?? '');
    $latitude    = !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : null;
    $longitude   = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

    $add_errors = [];
    if (empty($title))       $add_errors[] = 'Title is required.';
    if (empty($description)) $add_errors[] = 'Description is required.';
    if (empty($cost))        $add_errors[] = 'Cost is required.';
    if (empty($category))    $add_errors[] = 'Category is required.';

    if (!empty($image_url)) {
        $validated_url = filter_var($image_url, FILTER_VALIDATE_URL);
        if (!$validated_url || !in_array(parse_url($validated_url, PHP_URL_SCHEME), ['http','https'])) {
            $add_errors[] = 'Image URL must be a valid https:// URL.';
            $image_url = '';
        } else {
            $image_url = $validated_url;
        }
    }

    if (empty($add_errors)) {
        try {
            $db   = get_db();
            $stmt = $db->prepare(
                'INSERT INTO locations (title, description, cost, category, image_url, latitude, longitude)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $title, $description, $cost, $category,
                $image_url ?: null, $latitude, $longitude,
            ]);
            log_activity($admin_id, 'admin_location_added');
            set_flash('success', 'Location added successfully.');
            redirect('admin/manage_locations.php');
        } catch (PDOException $e) {
            error_log('[ADMIN ADD LOC] ' . $e->getMessage());
            set_flash('error', 'Could not add location.');
        }
    } else {
        set_flash('error', implode(' ', $add_errors));
    }
}

// ── POST: edit location ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_location'])) {
    require_csrf();

    $id          = clean_int($_POST['location_id'] ?? 0);
    $title       = clean_string($_POST['title']       ?? '', 300);
    $description = clean_string($_POST['description'] ?? '', 5000);
    $cost        = clean_string($_POST['cost']        ?? '', 100);
    $category    = clean_string($_POST['category']    ?? '', 100);
    $image_url   = trim($_POST['image_url'] ?? '');
    $latitude    = !empty($_POST['latitude'])  ? (float)$_POST['latitude']  : null;
    $longitude   = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;

    if (!empty($image_url)) {
        $vurl = filter_var($image_url, FILTER_VALIDATE_URL);
        $image_url = ($vurl && in_array(parse_url($vurl, PHP_URL_SCHEME), ['http','https']))
            ? $vurl : null;
    } else {
        $image_url = null;
    }

    if ($id > 0 && $title && $description && $cost && $category) {
        try {
            $db   = get_db();
            $stmt = $db->prepare(
                'UPDATE locations
                 SET title=?, description=?, cost=?, category=?, image_url=?,
                     latitude=?, longitude=?
                 WHERE id=?'
            );
            $stmt->execute([$title, $description, $cost, $category,
                            $image_url, $latitude, $longitude, $id]);
            log_activity($admin_id, 'admin_location_edited');
            set_flash('success', 'Location updated.');
        } catch (PDOException $e) {
            error_log('[ADMIN EDIT LOC] ' . $e->getMessage());
            set_flash('error', 'Could not update location.');
        }
    } else {
        set_flash('error', 'All required fields must be filled.');
    }
    redirect('admin/manage_locations.php');
}

// ── GET: list ─────────────────────────────────────────────────────────────
$locations = [];
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT l.id, l.title, l.category, l.cost, l.is_active,
                l.image_url, l.description, l.latitude, l.longitude,
                ROUND(AVG(r.rating),1) AS avg_rating,
                COUNT(DISTINCT r.id)   AS rating_count,
                COUNT(DISTINCT c.id)   AS comment_count
         FROM   locations l
         LEFT   JOIN ratings  r ON r.location_id = l.id
         LEFT   JOIN comments c ON c.location_id = l.id
         GROUP  BY l.id
         ORDER  BY l.created_at DESC'
    );
    $stmt->execute();
    $locations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[ADMIN LIST LOC] ' . $e->getMessage());
}

$categories = ['Nature', 'Adventure', 'Culture', 'Agri-Tourism', 'Accommodation', 'Heritage'];

require_once 'admin_header.php';
?>

<?= flash_alert('success') ?>
<?= flash_alert('error') ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <span style="font-size:.82rem;color:var(--admin-muted);">
        <?= count($locations) ?> location<?= count($locations) !== 1 ? 's' : '' ?>
    </span>
    <button class="btn btn-sm"
            style="background:#c0392b;color:#fff;"
            data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus me-1"></i>Add Location
    </button>
</div>

<div class="stat-card p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0 admin-table">
    <thead>
        <tr>
            <th class="px-3">Title</th>
            <th>Category</th>
            <th>Status</th>
            <th class="text-center">Rating</th>
            <th class="text-center">Comments</th>
            <th class="text-end pe-3">Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($locations as $loc): ?>
    <tr>
        <td class="px-3">
            <a href="../location.php?id=<?= (int)$loc['id'] ?>"
               class="fw-semibold text-decoration-none"
               style="color:#c0392b;" target="_blank">
                <?= e($loc['title']) ?>
            </a>
        </td>
        <td><?= e($loc['category']) ?></td>
        <td>
            <span class="badge <?= $loc['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                <?= $loc['is_active'] ? 'Active' : 'Inactive' ?>
            </span>
        </td>
        <td class="text-center" style="font-size:.82rem;">
            <?= $loc['rating_count'] > 0
                ? '★ ' . e(number_format((float)$loc['avg_rating'],1)) . ' (' . (int)$loc['rating_count'] . ')'
                : '—' ?>
        </td>
        <td class="text-center"><?= (int)$loc['comment_count'] ?></td>
        <td class="text-end pe-3">
            <div class="d-flex gap-1 justify-content-end">
                <!-- Edit -->
                <button class="btn btn-sm btn-outline-secondary"
                        data-bs-toggle="modal" data-bs-target="#editLocModal"
                        data-id="<?= (int)$loc['id'] ?>"
                        data-title="<?= eAttr($loc['title']) ?>"
                        data-description="<?= eAttr($loc['description']) ?>"
                        data-cost="<?= eAttr($loc['cost']) ?>"
                        data-category="<?= eAttr($loc['category']) ?>"
                        data-image="<?= eAttr($loc['image_url'] ?? '') ?>"
                        data-lat="<?= eAttr((string)($loc['latitude'] ?? '')) ?>"
                        data-lng="<?= eAttr((string)($loc['longitude'] ?? ''))?>">
                    <i class="bi bi-pencil"></i>
                </button>
                <!-- Toggle active -->
                <form method="POST" action="manage_locations.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="toggle_active" value="1">
                    <input type="hidden" name="location_id"   value="<?= (int)$loc['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                            title="<?= $loc['is_active'] ? 'Deactivate' : 'Activate' ?>">
                        <i class="bi <?= $loc['is_active'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                    </button>
                </form>
                <!-- Delete -->
                <form method="POST" action="manage_locations.php"
                      onsubmit="return confirm('Delete this location?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_location" value="1">
                    <input type="hidden" name="location_id"     value="<?= (int)$loc['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<!-- Add location modal -->
<div class="modal fade" id="addModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content" style="border-radius:14px;border:none;">
    <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Add New Location</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST" action="manage_locations.php">
        <?= csrf_field() ?>
        <input type="hidden" name="add_location" value="1">
        <div class="modal-body">
            <?php require '_loc_form_fields.php'; ?>
        </div>
        <div class="modal-footer border-0">
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm"
                    style="background:#c0392b;color:#fff;">Add Location</button>
        </div>
    </form>
</div>
</div>
</div>

<!-- Edit location modal -->
<div class="modal fade" id="editLocModal" tabindex="-1">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content" style="border-radius:14px;border:none;">
    <div class="modal-header border-0 pb-0">
        <h5 class="modal-title fw-bold">Edit Location</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
    </div>
    <form method="POST" action="manage_locations.php">
        <?= csrf_field() ?>
        <input type="hidden" name="edit_location" value="1">
        <input type="hidden" name="location_id"   id="edit-loc-id">
        <div class="modal-body">
            <?php require '_loc_form_fields.php'; ?>
        </div>
        <div class="modal-footer border-0">
            <button type="button" class="btn btn-outline-secondary btn-sm"
                    data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-sm"
                    style="background:#c0392b;color:#fff;">Save Changes</button>
        </div>
    </form>
</div>
</div>
</div>

<script>
document.getElementById('editLocModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    var modal = document.getElementById('editLocModal');
    document.getElementById('edit-loc-id').value = btn.dataset.id;
    modal.querySelector('[name="title"]').value       = btn.dataset.title;
    modal.querySelector('[name="description"]').value = btn.dataset.description;
    modal.querySelector('[name="cost"]').value        = btn.dataset.cost;
    modal.querySelector('[name="category"]').value    = btn.dataset.category;
    modal.querySelector('[name="image_url"]').value   = btn.dataset.image;
    modal.querySelector('[name="latitude"]').value    = btn.dataset.lat;
    modal.querySelector('[name="longitude"]').value   = btn.dataset.lng;
});
</script>

<?php require_once 'admin_footer.php'; ?>
