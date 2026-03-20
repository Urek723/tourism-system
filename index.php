<?php


require_once 'db.php';
require_once 'functions.php';

start_secure_session();

$page_title = 'Home';

// ── Handle contact form POST ──────────────────────────────────────────────
$contact_success = false;
$contact_errors  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {

    // 1. Verify CSRF token
    require_csrf();

    // 2. Validate and sanitise inputs
    $name    = clean_string($_POST['name']    ?? '', 150);
    $email   = clean_email($_POST['email']     ?? '');
    $subject = clean_string($_POST['subject'] ?? '', 255);
    $message = clean_string($_POST['message'] ?? '', 2000);

    if (empty($name))    $contact_errors[] = 'Name is required.';
    if (empty($email))   $contact_errors[] = 'A valid email address is required.';
    if (empty($subject)) $contact_errors[] = 'Subject is required.';
    if (empty($message)) $contact_errors[] = 'Message is required.';
    if (strlen($message) < 10) $contact_errors[] = 'Message must be at least 10 characters.';

    if (empty($contact_errors)) {
        // 3. Insert with prepared statement — no SQL injection possible
        try {
            $db   = get_db();
            $stmt = $db->prepare(
                'INSERT INTO inquiries (name, email, subject, message)
                 VALUES (:name, :email, :subject, :message)'
            );
            $stmt->execute([
                ':name'    => $name,
                ':email'   => $email,
                ':subject' => $subject,
                ':message' => $message,
            ]);
            $contact_success = true;
        } catch (PDOException $e) {
            error_log('[INQUIRY INSERT ERROR] ' . $e->getMessage());
            $contact_errors[] = 'Could not send your message. Please try again.';
        }
    }
}

// ── Fetch 3 featured locations ────────────────────────────────────────────
$featured = [];
try {
    $db   = get_db();
    $stmt = $db->prepare(
        'SELECT id, title, description, cost, category, image_url
         FROM locations
         WHERE is_active = 1
         ORDER BY created_at DESC
         LIMIT 3'
    );
    $stmt->execute();
    $featured = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('[FETCH FEATURED ERROR] ' . $e->getMessage());
}

require_once 'includes/header.php';
?>

<main>

<!-- ── Hero ──────────────────────────────────────────────────── -->
<section style="
    background: linear-gradient(135deg, var(--brand-dark) 0%, #1a6b3a 60%, #2d8f52 100%);
    padding: 6rem 0 5rem;
    position: relative;
    overflow: hidden;
">
    <!-- Decorative circles -->
    <div style="position:absolute;top:-80px;right:-80px;width:350px;height:350px;
                border-radius:50%;background:rgba(200,169,81,.08);pointer-events:none;"></div>
    <div style="position:absolute;bottom:-60px;left:-60px;width:250px;height:250px;
                border-radius:50%;background:rgba(255,255,255,.04);pointer-events:none;"></div>

    <div class="container text-center" style="position:relative;z-index:1;">
        <p class="section-eyebrow mb-3" style="color:var(--brand-gold);">
            South Cotabato, Philippines
        </p>
        <h1 style="font-size:clamp(2.4rem,6vw,4rem);color:#fff;line-height:1.1;
                   font-weight:900;margin-bottom:1.25rem;">
            Discover the Wonders<br>of <span style="color:var(--brand-gold);">Tupi</span>
        </h1>
        <p style="font-size:1.15rem;color:rgba(255,255,255,.75);
                  max-width:560px;margin:0 auto 2.5rem;line-height:1.7;">
            Pristine lakes, cascading waterfalls, indigenous culture, and
            breathtaking highland landscapes await you.
        </p>
        <div class="d-flex gap-3 justify-content-center flex-wrap">
            <a href="locations.php" class="btn btn-gold btn-lg px-5">
                <i class="bi bi-compass me-2"></i>Explore Destinations
            </a>
            <?php if (!is_logged_in()): ?>
            <a href="register.php" class="btn btn-outline-light btn-lg px-5">
                Create Account
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ── Stats strip ───────────────────────────────────────────── -->
<section style="background:#fff;border-bottom:1px solid #e8ede9;">
    <div class="container">
        <div class="row text-center py-4">
            <?php
            $stats = [
                ['bi-geo-alt-fill',  'Tourist Spots',      '6+'],
                ['bi-tree-fill',     'Nature Attractions',  '4'],
                ['bi-people-fill',   'Cultural Sites',      '2'],
                ['bi-star-fill',     'Visitor Rating',    '4.8'],
            ];
            foreach ($stats as [$icon, $label, $value]):
            ?>
            <div class="col-6 col-md-3 py-2">
                <i class="bi <?= $icon ?> fs-4 text-brand d-block mb-1"></i>
                <div style="font-size:1.8rem;font-weight:900;
                            font-family:var(--font-display);color:var(--brand-dark);">
                    <?= e($value) ?>
                </div>
                <div style="font-size:0.8rem;color:var(--brand-muted);
                            text-transform:uppercase;letter-spacing:.06em;font-weight:600;">
                    <?= e($label) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Featured Destinations ─────────────────────────────────── -->
<section class="py-5">
    <div class="container">
        <p class="section-eyebrow">Explore</p>
        <hr class="divider-brand">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <h2 style="font-size:2rem;margin:0;">Featured Destinations</h2>
            <a href="locations.php" class="btn-brand btn text-decoration-none">
                View All <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <?php if (empty($featured)): ?>
            <div class="alert alert-info">No destinations found yet.</div>
        <?php else: ?>
        <div class="row g-4">
            <?php foreach ($featured as $loc): ?>
            <div class="col-md-4">
                <div class="location-card card">
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

                    <div class="card-body">
                        <div class="d-flex gap-2 mb-2">
                            <span class="badge-category"><?= e($loc['category']) ?></span>
                            <span class="badge-cost">
                                <i class="bi bi-tag me-1"></i><?= e($loc['cost']) ?>
                            </span>
                        </div>
                        <h5 class="card-title"><?= e($loc['title']) ?></h5>
                        <p class="card-text">
                            <?= e(mb_substr($loc['description'], 0, 110)) ?>…
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-0 pb-3 px-3">
                        <a href="location.php?id=<?= (int)$loc['id'] ?>"
                           class="btn btn-brand w-100">
                            View Details <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ── Why Visit ──────────────────────────────────────────────── -->
<section style="background:#fff;" class="py-5">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-5">
                <p class="section-eyebrow">Why Tupi</p>
                <hr class="divider-brand">
                <h2 class="mb-3">A Hidden Gem in<br>South Cotabato</h2>
                <p style="color:var(--brand-muted);line-height:1.8;">
                    Nestled in the highlands of South Cotabato, Tupi is home to
                    the indigenous T'boli people, the stunning Lake Sebu, and the
                    thundering Seven Falls. Cool mountain air, rich biodiversity,
                    and warm local hospitality make it an unforgettable destination.
                </p>
                <a href="locations.php" class="btn btn-brand mt-2">
                    See All Attractions
                </a>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <?php
                    $features = [
                        ['bi-water',       'Nature & Lakes',    'Crystal-clear highland lakes and cascading waterfalls.'],
                        ['bi-people',      'Indigenous Culture', 'Experience authentic T\'boli weaving and traditions.'],
                        ['bi-lightning',   'Adventure',         'Ziplines, trekking, and outdoor thrills.'],
                        ['bi-flower1',     'Agri-Tourism',      'Strawberry farms and highland gardens.'],
                    ];
                    foreach ($features as [$icon, $title, $desc]):
                    ?>
                    <div class="col-6">
                        <div style="background:var(--brand-light);border-radius:12px;padding:1.5rem;">
                            <i class="bi <?= $icon ?> fs-3 text-brand d-block mb-2"></i>
                            <h6 style="font-weight:700;margin-bottom:.3rem;"><?= e($title) ?></h6>
                            <p style="font-size:.82rem;color:var(--brand-muted);margin:0;">
                                <?= e($desc) ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Contact / Inquiry Form ─────────────────────────────────── -->
<section class="py-5" id="contact">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <p class="section-eyebrow text-center">Get In Touch</p>
                <hr class="divider-brand mx-auto">
                <h2 class="text-center mb-1">Send Us a Message</h2>
                <p class="text-center mb-4" style="color:var(--brand-muted);">
                    Questions about visiting Tupi? We'd love to help.
                </p>

                <?php if ($contact_success): ?>
                    <div class="alert alert-success text-center">
                        <i class="bi bi-check-circle me-2"></i>
                        Thank you! Your message has been sent successfully.
                    </div>
                <?php else: ?>

                    <?php if (!empty($contact_errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0 ps-3">
                            <?php foreach ($contact_errors as $err): ?>
                                <li><?= e($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="auth-card p-4 p-md-5">
                        <form method="POST" action="index.php#contact" novalidate>
                            <?= csrf_field() ?>
                            <input type="hidden" name="contact_submit" value="1">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" name="name" class="form-control"
                                           maxlength="150" required
                                           value="<?= eAttr($_POST['name'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" name="email" class="form-control"
                                           maxlength="254" required
                                           value="<?= eAttr($_POST['email'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Subject *</label>
                                    <input type="text" name="subject" class="form-control"
                                           maxlength="255" required
                                           value="<?= eAttr($_POST['subject'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Message *</label>
                                    <textarea name="message" class="form-control"
                                              rows="5" maxlength="2000"
                                              required><?= e($_POST['message'] ?? '') ?></textarea>
                                    <div class="form-text">Maximum 2,000 characters.</div>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-brand w-100 py-2">
                                        <i class="bi bi-send me-2"></i>Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

</main>

<?php require_once 'includes/footer.php'; ?>
