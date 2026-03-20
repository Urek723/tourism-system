<?php

require_once 'db.php';
require_once 'functions.php';

start_secure_session();

$page_title = 'Destinations';

// Sanitise inputs (UNCHANGED)
$search   = clean_string($_GET['search']   ?? '', 100);
$category = clean_string($_GET['category'] ?? '', 50);

// Fetch valid categories (UNCHANGED)
$categories = [];
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT DISTINCT category FROM locations WHERE is_active = 1 ORDER BY category ASC'
    );
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log('[CATEGORIES FETCH] ' . $e->getMessage());
}

// Whitelist category (UNCHANGED)
if ($category && !in_array($category, $categories, true)) {
    $category = '';
}

// Build query — NOW also fetches avg_rating and comment_count
$locations = [];
$total     = 0;

try {
    $db         = get_db();
    $conditions = ['l.is_active = 1'];
    $params     = [];

    if (!empty($search)) {
        $conditions[] = '(l.title LIKE :search OR l.description LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }
    if (!empty($category)) {
        $conditions[] = 'l.category = :category';
        $params[':category'] = $category;
    }

    $where = implode(' AND ', $conditions);

    // Count
    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM locations l WHERE $where"
    );
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Fetch with aggregated rating + comment counts
    $stmt = $db->prepare(
        "SELECT l.id, l.title, l.description, l.cost, l.category, l.image_url,
                ROUND(AVG(r.rating), 1)  AS avg_rating,
                COUNT(DISTINCT r.id)     AS rating_count,
                COUNT(DISTINCT c.id)     AS comment_count
         FROM   locations l
         LEFT   JOIN ratings  r ON r.location_id = l.id
         LEFT   JOIN comments c ON c.location_id = l.id
         WHERE  $where
         GROUP  BY l.id
         ORDER  BY l.created_at DESC"
    );
    $stmt->execute($params);
    $locations = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[LOCATIONS FETCH] ' . $e->getMessage());
}

require_once 'includes/header.php';
?>

<main>

<!-- Page header (UNCHANGED) -->
<section style="background:var(--brand-dark);padding:3.5rem 0 2.5rem;">
    <div class="container">
        <p class="section-eyebrow" style="color:var(--brand-gold);">Explore</p>
        <h1 style="color:#fff;font-size:2.5rem;margin-bottom:.5rem;">All Destinations</h1>
        <p style="color:rgba(255,255,255,.6);margin:0;">
            <?= $total ?> destination<?= $total !== 1 ? 's' : '' ?> found
            <?= !empty($search) ? 'for "' . e($search) . '"' : '' ?>
            <?= !empty($category) ? 'in ' . e($category) : '' ?>
        </p>
    </div>
</section>

<!-- Search & Filter (UNCHANGED) -->
<section style="background:#fff;border-bottom:1px solid #e8ede9;" class="py-3">
    <div class="container">
        <form method="GET" action="locations.php" class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label">Search Destinations</label>
                <div class="input-group">
                    <span class="input-group-text bg-white">
                        <i class="bi bi-search text-brand"></i>
                    </span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Search by name or description…"
                           value="<?= eAttr($search) ?>"
                           maxlength="100">
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= eAttr($cat) ?>"
                            <?= $category === $cat ? 'selected' : '' ?>>
                        <?= e($cat) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-brand w-100">
                    <i class="bi bi-funnel me-1"></i>Filter
                </button>
            </div>
            <?php if (!empty($search) || !empty($category)): ?>
            <div class="col-md-2">
                <a href="locations.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x me-1"></i>Clear
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</section>

<!-- Results -->
<section class="py-5">
    <div class="container">

        <?php if (empty($locations)): ?>
        <div class="text-center py-5">
            <i class="bi bi-geo text-brand" style="font-size:3rem;opacity:.4;"></i>
            <h3 class="mt-3">No destinations found</h3>
            <p style="color:var(--brand-muted);">Try a different search term or clear filters.</p>
            <a href="locations.php" class="btn btn-brand">View All Destinations</a>
        </div>
        <?php else: ?>

        <div class="row g-4">
            <?php foreach ($locations as $loc): ?>
            <div class="col-md-6 col-lg-4">
                <div class="location-card card h-100">

                    <?php if (!empty($loc['image_url'])): ?>
                    <img src="<?= eAttr($loc['image_url']) ?>"
                         alt="<?= eAttr($loc['title']) ?>"
                         class="card-img-top"
                         loading="lazy"
                         onerror="this.src='https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=800'">
                    <?php else: ?>
                    <div class="card-img-top d-flex align-items-center justify-content-center"
                         style="background:#d4e8da;height:210px;">
                        <i class="bi bi-image text-brand" style="font-size:3rem;opacity:.4;"></i>
                    </div>
                    <?php endif; ?>

                    <div class="card-body d-flex flex-column">
                        <!-- Category + cost badges (UNCHANGED) -->
                        <div class="d-flex gap-2 mb-2 flex-wrap">
                            <span class="badge-category"><?= e($loc['category']) ?></span>
                            <span class="badge-cost">
                                <i class="bi bi-tag me-1"></i><?= e($loc['cost']) ?>
                            </span>
                        </div>

                        <h5 class="card-title"><?= e($loc['title']) ?></h5>
                        <p class="card-text flex-grow-1">
                            <?= e(mb_substr($loc['description'], 0, 120)) ?>…
                        </p>

                        <!-- NEW: rating + comment count under description -->
                        <div class="d-flex align-items-center gap-3 mt-2 mb-1"
                             style="font-size:.82rem;color:var(--brand-muted);">
                            <span>
                                <?php $rc = (int)$loc['rating_count']; ?>
                                <?php if ($rc > 0): ?>
                                    <i class="bi bi-star-fill" style="color:#e0b800;"></i>
                                    <?= e(number_format((float)$loc['avg_rating'], 1)) ?>
                                    <span style="color:#ccc;">·</span>
                                    <?= $rc ?> rating<?= $rc !== 1 ? 's' : '' ?>
                                <?php else: ?>
                                    <i class="bi bi-star" style="color:#ccc;"></i>
                                    No ratings yet
                                <?php endif; ?>
                            </span>
                            <span>
                                <i class="bi bi-chat-dots"></i>
                                <?= (int)$loc['comment_count'] ?>
                                comment<?= (int)$loc['comment_count'] !== 1 ? 's' : '' ?>
                            </span>
                        </div>

                        <div class="mt-3">
                            <a href="location.php?id=<?= (int)$loc['id'] ?>"
                               class="btn btn-brand w-100">
                                View Details <i class="bi bi-arrow-right ms-1"></i>
                            </a>
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

<?php require_once 'includes/footer.php'; ?>
