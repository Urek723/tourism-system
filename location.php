<?php
require_once 'db.php';
require_once 'functions.php';

start_secure_session();

$id = clean_int($_GET['id'] ?? 0);
if ($id === 0) {
    redirect('locations.php');
}

$location = null;
try {
    $db   = get_db();
    $stmt = $db->prepare('SELECT * FROM locations WHERE id = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$id]);
    $location = $stmt->fetch();
} catch (PDOException $e) {
    error_log('[LOC FETCH] ' . $e->getMessage());
}

if (!$location) {
    http_response_code(404);
    $page_title = 'Not Found';
    require_once 'includes/header.php';
    echo '<main class="py-5 text-center"><div class="container">
          <h2>Destination Not Found</h2>
          <a href="' . BASE_URL . 'locations.php" class="btn btn-brand">Back to Destinations</a>
          </div></main>';
    require_once 'includes/footer.php';
    exit;
}

// Rating data
$avg_rating = 0.0; $rating_count = 0; $user_rating = 0;
try {
    $stmt = $db->prepare(
        'SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS rating_count
         FROM ratings WHERE location_id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    $avg_rating   = (float)($row['avg_rating']   ?? 0);
    $rating_count = (int)  ($row['rating_count'] ?? 0);

    if (is_logged_in()) {
        $stmt = $db->prepare('SELECT rating FROM ratings WHERE location_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$id, (int)$_SESSION['user_id']]);
        $ex = $stmt->fetch();
        $user_rating = $ex ? (int)$ex['rating'] : 0;
    }
} catch (PDOException $e) {
    error_log('[LOC RATINGS] ' . $e->getMessage());
}

// Is it favorited?
$is_favorited = false;
if (is_logged_in()) {
    try {
        $stmt = $db->prepare(
            'SELECT id FROM favorites WHERE user_id = ? AND location_id = ?'
        );
        $stmt->execute([(int)$_SESSION['user_id'], $id]);
        $is_favorited = (bool)$stmt->fetch();
    } catch (PDOException $e) {
        error_log('[LOC FAV] ' . $e->getMessage());
    }
}

// Comments (approved only)
$comments = [];
try {
    $stmt = $db->prepare(
        'SELECT c.comment, c.created_at, u.username
         FROM   comments c JOIN users u ON u.id = c.user_id
         WHERE  c.location_id = ? AND c.status = "approved"
         ORDER  BY c.created_at DESC LIMIT 50'
    );
    $stmt->execute([$id]);
    $comments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[LOC COMMENTS] ' . $e->getMessage());
}

// Related locations
$related = [];
try {
    $stmt = $db->prepare(
        'SELECT id, title, cost, category, image_url FROM locations
         WHERE is_active = 1 AND id != ? ORDER BY RAND() LIMIT 3'
    );
    $stmt->execute([$id]);
    $related = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[LOC RELATED] ' . $e->getMessage());
}

$page_title = $location['title'];
$has_coords = !empty($location['latitude']) && !empty($location['longitude']);

// Trim any accidental trailing chars from the key constant
$maps_key = defined('GOOGLE_MAPS_KEY') ? rtrim(GOOGLE_MAPS_KEY, '&q=') : '';

require_once 'includes/header.php';
?>

<style>
.star-input-group { display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:4px; }
.star-input-group input[type="radio"] { display:none; }
.star-input-group label { font-size:2.2rem;color:#d0d0d0;cursor:pointer;line-height:1;transition:color .12s; }
.star-input-group label:hover,
.star-input-group label:hover ~ label,
.star-input-group input[type="radio"]:checked ~ label { color:#e0b800; }
.comment-item { padding:1rem 0;border-bottom:1px solid #edf1ee; }
.comment-item:last-child { border-bottom:none; }
.info-label { font-size:.72rem;text-transform:uppercase;letter-spacing:.07em;color:var(--brand-muted);
              font-weight:700;display:block;margin-bottom:.25rem; }
#location-map { width:100%;height:360px;border-radius:12px;border:1px solid #d4e8da;background:#f0f4f0; }
.fav-btn.saved i { color:#c0392b; }
</style>

<main>

<?php if (!empty($location['image_url'])): ?>
<div style="height:400px;overflow:hidden;position:relative;">
    <img src="<?= eAttr($location['image_url']) ?>" alt="<?= eAttr($location['title']) ?>"
         style="width:100%;height:100%;object-fit:cover;" onerror="this.style.display='none'">
    <div style="position:absolute;inset:0;background:linear-gradient(to bottom,transparent 50%,rgba(15,35,24,.85));"></div>
    <div style="position:absolute;bottom:2rem;left:50%;transform:translateX(-50%);text-align:center;color:#fff;width:90%;">
        <h1 style="font-size:clamp(1.8rem,5vw,3rem);text-shadow:0 2px 10px rgba(0,0,0,.4);">
            <?= e($location['title']) ?>
        </h1>
    </div>
</div>
<?php endif; ?>

<section class="py-5">
<div class="container">

    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb" style="font-size:.85rem;">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>index.php" class="text-brand">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>locations.php" class="text-brand">Destinations</a></li>
            <li class="breadcrumb-item active"><?= e($location['title']) ?></li>
        </ol>
    </nav>

    <?= flash_alert('success') ?>
    <?= flash_alert('error') ?>

    <div class="row g-5">

        <!-- LEFT: description + map + rating + comments -->
        <div class="col-lg-8">

            <?php if (empty($location['image_url'])): ?>
            <h1 class="mb-3"><?= e($location['title']) ?></h1>
            <?php endif; ?>

            <!-- Category + rating + FAVORITE button -->
            <div class="d-flex flex-wrap align-items-center gap-3 mb-4">
                <span class="badge-category fs-6 px-3 py-2">
                    <i class="bi bi-tag me-1"></i><?= e($location['category']) ?>
                </span>
                <span class="d-flex align-items-center gap-1">
                    <?php for ($s = 1; $s <= 5; $s++): ?>
                        <i class="bi <?= $avg_rating >= $s ? 'bi-star-fill' : ($avg_rating >= $s-.5 ? 'bi-star-half' : 'bi-star') ?>"
                           style="color:<?= $avg_rating >= $s ? '#e0b800' : '#ccc' ?>;"></i>
                    <?php endfor; ?>
                    <small style="color:var(--brand-muted);">
                        <?= $rating_count > 0
                            ? e(number_format($avg_rating,1)) . ' (' . $rating_count . ')'
                            : 'No ratings yet' ?>
                    </small>
                </span>

                <?php if (is_logged_in()): ?>
                <button id="fav-btn"
                        class="btn btn-sm <?= $is_favorited ? 'btn-danger' : 'btn-outline-danger' ?> fav-btn <?= $is_favorited ? 'saved' : '' ?>"
                        data-id="<?= $id ?>"
                        title="<?= $is_favorited ? 'Remove from favorites' : 'Save to favorites' ?>">
                    <i class="bi <?= $is_favorited ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                    <span><?= $is_favorited ? 'Saved' : 'Save' ?></span>
                </button>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <div style="font-size:1.05rem;line-height:1.85;color:#2a3e2d;">
                <?php
                $paragraphs = array_filter(explode("\n", $location['description']));
                foreach ($paragraphs as $p) echo '<p>' . e(trim($p)) . '</p>';
                ?>
            </div>

            <!-- Map -->
            <?php if ($has_coords): ?>
            <div class="mt-5" id="map-section">
                <h3 class="mb-1" style="font-size:1.3rem;">
                    <i class="bi bi-map text-brand me-2"></i>Location on Map
                </h3>
                <hr class="divider-brand">
                <div id="location-map"></div>
            </div>
            <?php endif; ?>

            <!-- Rating form -->
            <div class="mt-5" id="rating">
                <h3 class="mb-1" style="font-size:1.3rem;">
                    <i class="bi bi-star text-brand me-2"></i>Rate This Destination
                </h3>
                <hr class="divider-brand">
                <?php if (is_logged_in()): ?>
                <div class="auth-card p-4">
                    <form method="POST" action="<?= BASE_URL ?>add_rating.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="location_id" value="<?= $id ?>">
                        <div class="star-input-group mb-3" role="radiogroup">
                            <?php for ($star = 5; $star >= 1; $star--): ?>
                            <input type="radio" id="star<?= $star ?>" name="rating"
                                   value="<?= $star ?>" <?= $user_rating === $star ? 'checked' : '' ?> required>
                            <label for="star<?= $star ?>" title="<?= $star ?> star<?= $star > 1 ? 's' : '' ?>">&#9733;</label>
                            <?php endfor; ?>
                        </div>
                        <button type="submit" class="btn btn-brand">
                            <i class="bi bi-star-fill me-2"></i>
                            <?= $user_rating > 0 ? 'Update Rating' : 'Submit Rating' ?>
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="p-4 rounded-3" style="background:var(--brand-light);border:1px dashed #b8d4bf;">
                    <p class="mb-0" style="color:var(--brand-muted);">
                        <i class="bi bi-lock me-2"></i>
                        <a href="<?= BASE_URL ?>login.php" class="text-brand fw-semibold">Sign in</a> to rate.
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Comments -->
            <div class="mt-5" id="comments">
                <h3 class="mb-1" style="font-size:1.3rem;">
                    <i class="bi bi-chat-dots text-brand me-2"></i>
                    Comments <span style="font-size:1rem;font-weight:400;color:var(--brand-muted);">(<?= count($comments) ?>)</span>
                </h3>
                <hr class="divider-brand">

                <?php if (is_logged_in()): ?>
                <div class="auth-card p-4 mb-4">
                    <form method="POST" action="<?= BASE_URL ?>add_comment.php" novalidate>
                        <?= csrf_field() ?>
                        <input type="hidden" name="location_id" value="<?= $id ?>">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Share Your Experience</label>
                            <textarea name="comment" id="comment" class="form-control" rows="3"
                                      maxlength="1000" required
                                      placeholder="What did you think of <?= eAttr($location['title']) ?>?"></textarea>
                            <div class="form-text d-flex justify-content-between">
                                <span>Minimum 3 characters.</span>
                                <span id="char-count">0 / 1000</span>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-brand">
                            <i class="bi bi-send me-2"></i>Post Comment
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="p-4 rounded-3 mb-4" style="background:var(--brand-light);border:1px dashed #b8d4bf;">
                    <p class="mb-0" style="color:var(--brand-muted);">
                        <i class="bi bi-lock me-2"></i>
                        <a href="<?= BASE_URL ?>login.php" class="text-brand fw-semibold">Sign in</a> to comment.
                    </p>
                </div>
                <?php endif; ?>

                <?php if (empty($comments)): ?>
                <p style="color:var(--brand-muted);">No approved comments yet — be the first!</p>
                <?php else: ?>
                <div>
                    <?php foreach ($comments as $c): ?>
                    <div class="comment-item">
                        <div class="d-flex justify-content-between align-items-baseline flex-wrap gap-1">
                            <span style="font-weight:700;font-size:.875rem;color:var(--brand-green);">
                                <i class="bi bi-person-circle me-1"></i><?= e($c['username']) ?>
                            </span>
                            <span style="font-size:.78rem;color:var(--brand-muted);">
                                <?= e(date('M j, Y \a\t g:i A', strtotime($c['created_at']))) ?>
                            </span>
                        </div>
                        <div style="margin-top:.4rem;line-height:1.75;white-space:pre-wrap;word-break:break-word;">
                            <?= e($c['comment']) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: sidebar -->
        <div class="col-lg-4">
            <div class="auth-card p-4 sticky-top" style="top:80px;">
                <h5 class="mb-3" style="font-size:1.1rem;">
                    <i class="bi bi-info-circle text-brand me-2"></i>Quick Info
                </h5>
                <ul class="list-unstyled mb-0">
                    <li class="py-2" style="border-bottom:1px solid #e8ede9;">
                        <span class="info-label">Destination</span>
                        <span class="fw-semibold"><?= e($location['title']) ?></span>
                    </li>
                    <li class="py-2" style="border-bottom:1px solid #e8ede9;">
                        <span class="info-label">Category</span>
                        <span class="badge-category"><?= e($location['category']) ?></span>
                    </li>
                    <li class="py-2" style="border-bottom:1px solid #e8ede9;">
                        <span class="info-label">Entry Cost</span>
                        <span class="badge-cost"><i class="bi bi-tag me-1"></i><?= e($location['cost']) ?></span>
                    </li>
                    <li class="py-2" style="border-bottom:1px solid #e8ede9;">
                        <span class="info-label">Average Rating</span>
                        <?php if ($rating_count > 0): ?>
                        <span><?= e(number_format($avg_rating,1)) ?>/5 (<?= $rating_count ?>)</span>
                        <?php else: ?>
                        <span style="color:var(--brand-muted);">Not yet rated</span>
                        <?php endif; ?>
                    </li>
                    <li class="py-2">
                        <span class="info-label">Location</span>
                        <span class="fw-semibold"><i class="bi bi-geo-alt text-brand me-1"></i>Tupi, South Cotabato</span>
                    </li>
                </ul>

                <?php if (is_logged_in()): ?>
                <div class="mt-4 d-grid gap-2">
                    <a href="<?= BASE_URL ?>trip_planner.php" class="btn btn-brand">
                        <i class="bi bi-map me-2"></i>Add to Trip Plan
                    </a>
                    <a href="<?= BASE_URL ?>bookings.php" class="btn btn-outline-secondary">
                        <i class="bi bi-calendar-check me-2"></i>Book a Visit
                    </a>
                    <a href="<?= BASE_URL ?>index.php#contact" class="btn btn-outline-secondary">
                        <i class="bi bi-envelope me-2"></i>Inquire
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- Related -->
    <?php if (!empty($related)): ?>
    <div class="mt-5 pt-4" style="border-top:1px solid #e8ede9;">
        <h3 class="mb-1" style="font-size:1.4rem;">You May Also Like</h3>
        <hr class="divider-brand">
        <div class="row g-4">
            <?php foreach ($related as $rel): ?>
            <div class="col-md-4">
                <div class="location-card card">
                    <?php if (!empty($rel['image_url'])): ?>
                    <img src="<?= eAttr($rel['image_url']) ?>" alt="<?= eAttr($rel['title']) ?>"
                         class="card-img-top" loading="lazy" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="card-body">
                        <span class="badge-category mb-2 d-inline-block"><?= e($rel['category']) ?></span>
                        <h6 class="card-title"><?= e($rel['title']) ?></h6>
                        <span class="badge-cost"><?= e($rel['cost']) ?></span>
                    </div>
                    <div class="card-footer bg-transparent border-0 px-3 pb-3">
                        <a href="<?= BASE_URL ?>location.php?id=<?= (int)$rel['id'] ?>" class="btn btn-brand btn-sm w-100">View</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
</section>
</main>

<?php if ($has_coords && !empty($maps_key)): ?>
<script>
(function () {
    var LAT   = <?= json_encode((float)$location['latitude'])  ?>;
    var LNG   = <?= json_encode((float)$location['longitude']) ?>;
    var TITLE = <?= json_encode($location['title']) ?>;

    function initMap() {
        var pos = { lat: LAT, lng: LNG };
        var map = new google.maps.Map(document.getElementById('location-map'), {
            zoom: 14, center: pos, streetViewControl: false
        });
        var marker = new google.maps.Marker({
            position: pos, map: map, title: TITLE, animation: google.maps.Animation.DROP
        });
        var info = new google.maps.InfoWindow({
            content: '<strong>' + TITLE.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</strong>'
        });
        marker.addListener('click', function () { info.open(map, marker); });
        info.open(map, marker);
    }
    window.initMap = initMap;
}());
</script>
<script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?= eAttr($maps_key) ?>&callback=initMap">
</script>
<?php elseif ($has_coords && empty($maps_key)): ?>
<div class="container pb-3">
    <div class="alert alert-warning" style="font-size:.85rem;">
        <i class="bi bi-info-circle me-2"></i>
        Map unavailable — define <code>GOOGLE_MAPS_KEY</code> in <code>db.php</code> to enable it.
    </div>
</div>
<?php endif; ?>

<?php if (is_logged_in()): ?>
<script>
(function () {
    var csrf = <?= json_encode(csrf_token()) ?>;
    var btn  = document.getElementById('fav-btn');
    if (!btn) return;

    btn.addEventListener('click', function () {
        fetch('<?= BASE_URL ?>favorites.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf_token: csrf, location_id: <?= $id ?> })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (typeof data.saved === 'undefined') return;
            if (data.saved) {
                btn.classList.add('btn-danger', 'saved');
                btn.classList.remove('btn-outline-danger');
                btn.querySelector('i').className = 'bi bi-heart-fill';
                btn.querySelector('span').textContent = 'Saved';
            } else {
                btn.classList.remove('btn-danger', 'saved');
                btn.classList.add('btn-outline-danger');
                btn.querySelector('i').className = 'bi bi-heart';
                btn.querySelector('span').textContent = 'Save';
            }
        })
        .catch(function () {});
    });
}());
</script>
<?php endif; ?>

<script>
(function () {
    var ta  = document.getElementById('comment');
    var cnt = document.getElementById('char-count');
    if (!ta || !cnt) return;
    ta.addEventListener('input', function () {
        var n = ta.value.length;
        cnt.textContent = n + ' / 1000';
        cnt.style.color = n > 900 ? '#c0392b' : '';
    });
}());
</script>

<?php require_once 'includes/footer.php'; ?>