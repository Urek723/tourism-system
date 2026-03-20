<?php

require_once '../db.php';
require_once '../functions.php';

start_secure_session();
require_admin();
if (empty($_SESSION['admin_session'])) {
    destroy_session();
    redirect('admin_login.php');
}
$page_title = 'Admin Panel';

// ── Handle: delete location ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_location'])) {
    require_csrf();
    $del_id = clean_int($_POST['location_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $db   = get_db();
            $stmt = $db->prepare('DELETE FROM locations WHERE id = ?');
            $stmt->execute([$del_id]);
            set_flash('success', 'Location deleted successfully.');
        } catch (PDOException $e) {
            error_log('[ADMIN DELETE LOCATION] ' . $e->getMessage());
            set_flash('error', 'Could not delete location.');
        }
    }
    redirect('admin/admin_panel.php');
}

// ── Handle: update coordinates ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_coords'])) {
    require_csrf();

    $loc_id = clean_int($_POST['location_id'] ?? 0);

    // SECURITY: cast to float — prevents injection; NULL if blank
    $lat_raw = trim($_POST['latitude']  ?? '');
    $lng_raw = trim($_POST['longitude'] ?? '');
    $lat     = $lat_raw !== '' ? (float)$lat_raw : null;
    $lng     = $lng_raw !== '' ? (float)$lng_raw : null;

    // Validate coordinate ranges
    $coord_errors = [];
    if ($lat !== null && ($lat < -90  || $lat > 90)) {
        $coord_errors[] = 'Latitude must be between -90 and 90.';
    }
    if ($lng !== null && ($lng < -180 || $lng > 180)) {
        $coord_errors[] = 'Longitude must be between -180 and 180.';
    }

    if (empty($coord_errors) && $loc_id > 0) {
        try {
            $db   = get_db();
            $stmt = $db->prepare(
                'UPDATE locations SET latitude = ?, longitude = ? WHERE id = ?'
            );
            $stmt->execute([$lat, $lng, $loc_id]);
            set_flash('success', 'Coordinates updated successfully.');
        } catch (PDOException $e) {
            error_log('[ADMIN UPDATE COORDS] ' . $e->getMessage());
            set_flash('error', 'Could not update coordinates.');
        }
    } else {
        set_flash('error', implode(' ', $coord_errors) ?: 'Invalid location ID.');
    }
    redirect('admin/admin_panel.php');
}

// ── Handle: delete inquiry ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_inquiry'])) {
    require_csrf();
    $del_id = clean_int($_POST['inquiry_id'] ?? 0);
    if ($del_id > 0) {
        try {
            $db   = get_db();
            $stmt = $db->prepare('DELETE FROM inquiries WHERE id = ?');
            $stmt->execute([$del_id]);
            set_flash('success', 'Inquiry deleted.');
        } catch (PDOException $e) {
            error_log('[ADMIN DELETE INQUIRY] ' . $e->getMessage());
            set_flash('error', 'Could not delete inquiry.');
        }
    }
    redirect('admin/admin_panel.php');
}

// ── Fetch locations with rating + comment counts ──────────────────────────
$locations = [];
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT l.id, l.title, l.category, l.cost, l.is_active,
                l.latitude, l.longitude, l.created_at,
                ROUND(AVG(r.rating), 1)  AS avg_rating,
                COUNT(DISTINCT r.id)     AS rating_count,
                COUNT(DISTINCT c.id)     AS comment_count
         FROM   locations l
         LEFT   JOIN ratings  r ON r.location_id = l.id
         LEFT   JOIN comments c ON c.location_id = l.id
         GROUP  BY l.id
         ORDER  BY l.created_at DESC'
    );
    $stmt->execute();
    $locations = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[ADMIN FETCH LOCATIONS] ' . $e->getMessage());
}

// ── Fetch inquiries ───────────────────────────────────────────────────────
$inquiries = [];
try {
    $stmt = $db->prepare(
        'SELECT id, name, email, subject, is_read, created_at
         FROM   inquiries
         ORDER  BY created_at DESC
         LIMIT  20'
    );
    $stmt->execute();
    $inquiries = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[ADMIN FETCH INQUIRIES] ' . $e->getMessage());
}

// ── Summary stats ─────────────────────────────────────────────────────────
$stats = ['active' => 0, 'users' => 0, 'unread' => 0, 'ratings' => 0, 'comments' => 0];
try {
    $stats['active']   = (int)$db->query('SELECT COUNT(*) FROM locations WHERE is_active = 1')->fetchColumn();
    $stats['users']    = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $stats['unread']   = (int)$db->query('SELECT COUNT(*) FROM inquiries WHERE is_read = 0')->fetchColumn();
    $stats['ratings']  = (int)$db->query('SELECT COUNT(*) FROM ratings')->fetchColumn();
    $stats['comments'] = (int)$db->query('SELECT COUNT(*) FROM comments')->fetchColumn();
} catch (PDOException $e) {
    error_log('[ADMIN STATS] ' . $e->getMessage());
}

require_once '../includes/header.php';
?>

<main class="py-5">
    <div class="container">

        <!-- Page header -->
        <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
            <div>
                <p class="section-eyebrow mb-1">
                    <i class="bi bi-shield-lock me-1"></i>Admin Panel
                </p>
                <h1 style="font-size:1.9rem;margin:0;">Dashboard</h1>
            </div>
            <a href="../add_location.php" class="btn btn-brand">
                <i class="bi bi-plus-circle me-2"></i>Add Location
            </a>
        </div>

        <?= flash_alert('success') ?>
        <?= flash_alert('error') ?>

        <!-- Stats strip -->
        <div class="row g-3 mb-5">
            <?php
            $cards = [
                ['bi-geo-alt-fill',   'Active Locations', $stats['active'],   '#d4e8da'],
                ['bi-people-fill',    'Registered Users', $stats['users'],    '#dce8f5'],
                ['bi-star-fill',      'Total Ratings',    $stats['ratings'],  '#fff3cd'],
                ['bi-chat-dots-fill', 'Total Comments',   $stats['comments'], '#f5d4e8'],
                ['bi-envelope-fill',  'Unread Inquiries', $stats['unread'],   '#fce8d5'],
            ];
            foreach ($cards as [$icon, $label, $val, $bg]):
            ?>
            <div class="col-6 col-md-4 col-xl">
                <div class="p-3 rounded-3 d-flex align-items-center gap-3"
                     style="background:#fff;box-shadow:0 1px 8px rgba(0,0,0,.06);">
                    <div style="width:46px;height:46px;background:<?= $bg ?>;
                                border-radius:10px;display:flex;align-items:center;
                                justify-content:center;flex-shrink:0;">
                        <i class="bi <?= $icon ?> text-brand" style="font-size:1.1rem;"></i>
                    </div>
                    <div>
                        <div style="font-size:1.5rem;font-weight:800;
                                    font-family:var(--font-display);line-height:1;">
                            <?= $val ?>
                        </div>
                        <div style="font-size:.7rem;text-transform:uppercase;
                                    letter-spacing:.06em;color:var(--brand-muted);
                                    font-weight:600;">
                            <?= e($label) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Locations table -->
        <h2 class="mb-1" style="font-size:1.4rem;">Manage Locations</h2>
        <hr class="divider-brand">

        <?php if (empty($locations)): ?>
            <div class="alert alert-info">No locations yet.</div>
        <?php else: ?>
        <div class="card border-0 shadow-sm rounded-3 mb-5">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0"
                       style="font-size:.875rem;">
                    <thead style="background:var(--brand-light);">
                        <tr>
                            <th class="px-3 py-3">Title</th>
                            <th class="py-3">Category</th>
                            <th class="py-3">Status</th>
                            <th class="py-3 text-center">Rating</th>
                            <th class="py-3 text-center">Comments</th>
                            <th class="py-3 text-center">Map</th>
                            <th class="py-3 text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($locations as $loc): ?>
                        <tr>
                            <td class="px-3">
                                <a href="../location.php?id=<?= (int)$loc['id'] ?>"
                                   class="text-brand fw-semibold text-decoration-none">
                                    <?= e($loc['title']) ?>
                                </a>
                            </td>
                            <td><?= e($loc['category']) ?></td>
                            <td>
                                <?php if ($loc['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php if ($loc['rating_count'] > 0): ?>
                                    <span class="badge"
                                          style="background:#fff3cd;color:#7a5c00;
                                                 font-weight:600;">
                                        ★ <?= e(number_format((float)$loc['avg_rating'], 1)) ?>
                                        <small>(<?= (int)$loc['rating_count'] ?>)</small>
                                    </span>
                                <?php else: ?>
                                    <span style="color:var(--brand-muted);font-size:.78rem;">
                                        —
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-light text-dark border">
                                    <?= (int)$loc['comment_count'] ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($loc['latitude'])): ?>
                                    <i class="bi bi-geo-alt-fill text-success"
                                       title="<?= eAttr((string)$loc['latitude']) ?>,
                                              <?= eAttr((string)$loc['longitude']) ?>">
                                    </i>
                                <?php else: ?>
                                    <i class="bi bi-geo-alt text-muted"
                                       title="No coordinates set"></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-end pe-3">
                                <div class="d-flex gap-1 justify-content-end">
                                    <!-- Set / Edit coordinates -->
                                    <button type="button"
                                            class="btn btn-sm btn-outline-secondary"
                                            title="Set map coordinates"
                                            data-bs-toggle="modal"
                                            data-bs-target="#coordsModal"
                                            data-loc-id="<?= (int)$loc['id'] ?>"
                                            data-loc-title="<?= eAttr($loc['title']) ?>"
                                            data-lat="<?= eAttr((string)($loc['latitude'] ?? '')) ?>"
                                            data-lng="<?= eAttr((string)($loc['longitude'] ?? '')) ?>">
                                        <i class="bi bi-map"></i>
                                    </button>
                                    <!-- Delete -->
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Delete location"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteModal"
                                            data-location-id="<?= (int)$loc['id'] ?>"
                                            data-location-title="<?= eAttr($loc['title']) ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Inquiries table (unchanged) -->
        <h2 class="mb-1" style="font-size:1.4rem;">Contact Inquiries</h2>
        <hr class="divider-brand">

        <?php if (empty($inquiries)): ?>
            <div class="alert alert-info">No inquiries received yet.</div>
        <?php else: ?>
        <div class="card border-0 shadow-sm rounded-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0"
                       style="font-size:.875rem;">
                    <thead style="background:var(--brand-light);">
                        <tr>
                            <th class="px-3 py-3">From</th>
                            <th class="py-3">Email</th>
                            <th class="py-3">Subject</th>
                            <th class="py-3">Status</th>
                            <th class="py-3">Received</th>
                            <th class="py-3 text-end pe-3">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($inquiries as $inq): ?>
                        <tr <?= !$inq['is_read'] ? 'style="font-weight:600;"' : '' ?>>
                            <td class="px-3"><?= e($inq['name']) ?></td>
                            <td><?= e($inq['email']) ?></td>
                            <td><?= e($inq['subject']) ?></td>
                            <td>
                                <?php if (!$inq['is_read']): ?>
                                    <span class="badge bg-warning text-dark">Unread</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Read</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--brand-muted);">
                                <?= e(date('M j, Y', strtotime($inq['created_at']))) ?>
                            </td>
                            <td class="text-end pe-3">
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteInquiryModal"
                                        data-inquiry-id="<?= (int)$inq['id'] ?>"
                                        data-inquiry-subject="<?= eAttr($inq['subject']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- ── Set Coordinates Modal (NEW) ───────────────────────────── -->
<div class="modal fade" id="coordsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="font-family:var(--font-display);">
                    <i class="bi bi-map text-brand me-2"></i>Set Map Coordinates
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php" id="coordsForm">
                <?= csrf_field() ?>
                <input type="hidden" name="update_coords" value="1">
                <input type="hidden" name="location_id"   id="coordsLocId">

                <div class="modal-body pt-2">
                    <p class="text-muted" style="font-size:.88rem;" id="coordsLocTitle"></p>

                    <div class="row g-3">
                        <div class="col-6">
                            <label for="coordsLat" class="form-label">Latitude</label>
                            <input type="number"
                                   id="coordsLat"
                                   name="latitude"
                                   class="form-control"
                                   step="0.000001"
                                   min="-90" max="90"
                                   placeholder="e.g. 6.293300">
                            <div class="form-text">-90 to 90</div>
                        </div>
                        <div class="col-6">
                            <label for="coordsLng" class="form-label">Longitude</label>
                            <input type="number"
                                   id="coordsLng"
                                   name="longitude"
                                   class="form-control"
                                   step="0.000001"
                                   min="-180" max="180"
                                   placeholder="e.g. 124.776900">
                            <div class="form-text">-180 to 180</div>
                        </div>
                    </div>

                    <p class="mt-3 mb-0" style="font-size:.8rem;color:var(--brand-muted);">
                        <i class="bi bi-info-circle me-1"></i>
                        Find coordinates on
                        <a href="https://maps.google.com" target="_blank"
                           rel="noopener noreferrer">Google Maps</a>
                        (right-click a spot → "What's here?").
                        Leave blank to remove the map from this location.
                    </p>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-secondary"
                            data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-brand">
                        <i class="bi bi-save me-1"></i>Save Coordinates
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Delete Location Modal (UNCHANGED) ─────────────────────── -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="font-family:var(--font-display);">
                    <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                    Confirm Delete
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p>Permanently delete
                   <strong id="deleteLocationTitle"></strong>?
                   All ratings and comments will also be removed.
                   This cannot be undone.
                </p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="index.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_location" value="1">
                    <input type="hidden" name="location_id" id="deleteLocationId">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ── Delete Inquiry Modal (UNCHANGED) ──────────────────────── -->
<div class="modal fade" id="deleteInquiryModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:14px;border:none;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="font-family:var(--font-display);">
                    <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                    Delete Inquiry
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <p>Delete inquiry: <strong id="deleteInquirySubject"></strong>?</p>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-outline-secondary"
                        data-bs-dismiss="modal">Cancel</button>
                <form method="POST" action="index.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="delete_inquiry" value="1">
                    <input type="hidden" name="inquiry_id" id="deleteInquiryId">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>Delete
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * SECURITY: All modal data populated via .textContent / .value
 * — NOT innerHTML — so data-attributes cannot inject HTML/scripts.
 */
document.getElementById('coordsModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('coordsLocId').value       = btn.dataset.locId;
    document.getElementById('coordsLat').value         = btn.dataset.lat  || '';
    document.getElementById('coordsLng').value         = btn.dataset.lng  || '';
    document.getElementById('coordsLocTitle').textContent =
        'Location: ' + btn.dataset.locTitle;
});

document.getElementById('deleteModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteLocationId').value          = btn.dataset.locationId;
    document.getElementById('deleteLocationTitle').textContent = btn.dataset.locationTitle;
});

document.getElementById('deleteInquiryModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('deleteInquiryId').value            = btn.dataset.inquiryId;
    document.getElementById('deleteInquirySubject').textContent = btn.dataset.inquirySubject;
});
</script>

<?php require_once '../includes/footer.php'; ?>
