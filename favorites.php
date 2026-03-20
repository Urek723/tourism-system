<?php
/**
 * favorites.php
 * GET  → list the logged-in user's saved destinations
 * POST → toggle favorite (add / remove); returns JSON for AJAX
 *
 * Security: require_login, CSRF, clean_int, PDO, ownership enforced via user_id from session.
 */

require_once 'db.php';
require_once 'functions.php';
require_once 'activity_logger.php';

start_secure_session();
require_login();

$user_id = (int)$_SESSION['user_id'];

// POST: toggle favorite via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $body        = json_decode(file_get_contents('php://input'), true) ?? [];
    $token       = $body['csrf_token'] ?? '';
    $location_id = clean_int($body['location_id'] ?? 0);

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        echo json_encode(['error' => 'CSRF validation failed']);
        exit;
    }

    if ($location_id === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid location']);
        exit;
    }

    try {
        $db = get_db();

        // Verify location exists
        $chk = $db->prepare('SELECT id FROM locations WHERE id = ? AND is_active = 1');
        $chk->execute([$location_id]);
        if (!$chk->fetch()) {
            http_response_code(404);
            echo json_encode(['error' => 'Location not found']);
            exit;
        }

        // Check current state
        $chk = $db->prepare(
            'SELECT id FROM favorites WHERE user_id = ? AND location_id = ?'
        );
        $chk->execute([$user_id, $location_id]);
        $exists = $chk->fetch();

        if ($exists) {
            $db->prepare('DELETE FROM favorites WHERE user_id = ? AND location_id = ?')
               ->execute([$user_id, $location_id]);
            log_activity($user_id, 'favorite_removed');
            echo json_encode(['saved' => false]);
        } else {
            $db->prepare(
                'INSERT IGNORE INTO favorites (user_id, location_id) VALUES (?, ?)'
            )->execute([$user_id, $location_id]);
            log_activity($user_id, 'favorite_added');
            echo json_encode(['saved' => true]);
        }
    } catch (PDOException $e) {
        error_log('[FAVORITES TOGGLE] ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Server error']);
    }
    exit;
}

// GET: display favorites list
$page_title = 'My Favorites';
$favorites  = [];
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT l.id, l.title, l.category, l.cost, l.image_url, l.description,
                ROUND(AVG(r.rating), 1) AS avg_rating
         FROM   favorites  f
         JOIN   locations  l ON l.id = f.location_id
         LEFT   JOIN ratings r ON r.location_id = l.id
         WHERE  f.user_id = ? AND l.is_active = 1
         GROUP  BY l.id
         ORDER  BY f.created_at DESC'
    );
    $stmt->execute([$user_id]);
    $favorites = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[FAVORITES LIST] ' . $e->getMessage());
}

require_once 'includes/header.php';
?>

<main>
<section style="background:var(--brand-dark);padding:3.5rem 0 2.5rem;">
    <div class="container">
        <p class="section-eyebrow" style="color:var(--brand-gold);">My Account</p>
        <h1 style="color:#fff;font-size:2.5rem;margin-bottom:.5rem;">My Favorites</h1>
        <p style="color:rgba(255,255,255,.6);margin:0;">
            <?= count($favorites) ?> saved destination<?= count($favorites) !== 1 ? 's' : '' ?>
        </p>
    </div>
</section>

<section class="py-5">
<div class="container">
    <?= flash_alert('success') ?>

    <?php if (empty($favorites)): ?>
    <div class="text-center py-5">
        <i class="bi bi-heart text-brand" style="font-size:3rem;opacity:.4;"></i>
        <h3 class="mt-3">No favorites yet</h3>
        <p style="color:var(--brand-muted);">
            Browse destinations and tap the heart icon to save them here.
        </p>
        <a href="locations.php" class="btn btn-brand mt-2">Explore Destinations</a>
    </div>
    <?php else: ?>
    <div class="row g-4">
        <?php foreach ($favorites as $loc): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card location-card h-100">
                <?php if (!empty($loc['image_url'])): ?>
                <img src="<?= eAttr($loc['image_url']) ?>"
                     alt="<?= eAttr($loc['title']) ?>"
                     class="card-img-top" loading="lazy">
                <?php endif; ?>
                <div class="card-body d-flex flex-column">
                    <div class="d-flex gap-2 mb-2 flex-wrap">
                        <span class="badge-category"><?= e($loc['category']) ?></span>
                        <span class="badge-cost"><?= e($loc['cost']) ?></span>
                    </div>
                    <h5 class="card-title"><?= e($loc['title']) ?></h5>
                    <p class="card-text flex-grow-1">
                        <?= e(mb_substr(strip_tags($loc['description']), 0, 100)) ?>…
                    </p>
                    <div class="d-flex gap-2 mt-3">
                        <a href="location.php?id=<?= (int)$loc['id'] ?>"
                           class="btn btn-brand btn-sm flex-grow-1">View</a>
                        <button class="btn btn-sm btn-outline-danger fav-remove"
                                data-id="<?= (int)$loc['id'] ?>"
                                title="Remove from favorites">
                            <i class="bi bi-heart-fill"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</section>
</main>

<script>
(function () {
    var csrf = <?= json_encode(csrf_token()) ?>;

    document.querySelectorAll('.fav-remove').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var card = btn.closest('.col-md-6, .col-lg-4, .col-md-6.col-lg-4');
            fetch('favorites.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ csrf_token: csrf, location_id: parseInt(btn.dataset.id) })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.saved === false && card) {
                    card.remove();
                }
            });
        });
    });
}());
</script>

<?php require_once 'includes/footer.php'; ?>