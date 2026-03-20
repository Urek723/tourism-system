<?php
/**
 * helpline.php — Static Helpline / Emergency Contacts Page
 */

require_once 'db.php';
require_once 'functions.php';

start_secure_session();

$page_title = 'Helpline';

require_once 'includes/header.php';
?>

<main>

<section style="background:var(--brand-dark);padding:3.5rem 0 2.5rem;">
    <div class="container">
        <p class="section-eyebrow" style="color:var(--brand-gold);">Support</p>
        <h1 style="color:#fff;font-size:2.5rem;margin-bottom:.5rem;">Helpline</h1>
        <p style="color:rgba(255,255,255,.6);margin:0;">
            Reach us anytime for travel assistance or emergencies.
        </p>
    </div>
</section>

<section class="py-5">
<div class="container">
<div class="row g-4 justify-content-center">

    <?php
    $contacts = [
        [
            'icon'    => 'bi-telephone-fill',
            'color'   => '#27ae60',
            'bg'      => '#e8fce8',
            'title'   => 'Phone',
            'lines'   => ['+63 (083) 123-4567', '+63 917 123 4567 (Mobile)'],
            'note'    => 'Mon – Fri, 8:00 AM – 5:00 PM',
        ],
        [
            'icon'    => 'bi-envelope-fill',
            'color'   => '#1a4fa0',
            'bg'      => '#e8f0fc',
            'title'   => 'Email',
            'lines'   => ['tourism@tupi.gov.ph', 'info@tupitourism.ph'],
            'note'    => 'We reply within 24 hours.',
        ],
        [
            'icon'    => 'bi-geo-alt-fill',
            'color'   => '#c0392b',
            'bg'      => '#fce8e8',
            'title'   => 'Office Address',
            'lines'   => ['Municipal Tourism Office', 'Tupi, South Cotabato 9505', 'Philippines'],
            'note'    => null,
        ],
        [
            'icon'    => 'bi-exclamation-triangle-fill',
            'color'   => '#e67e22',
            'bg'      => '#fff3e0',
            'title'   => 'Emergency',
            'lines'   => ['911 (National Emergency)', 'PNP Tupi: (083) 876-5432', 'BFP Tupi: (083) 876-1234'],
            'note'    => 'Available 24 / 7',
        ],
    ];
    foreach ($contacts as $c):
    ?>
    <div class="col-md-6 col-lg-5">
        <div class="p-4 rounded-3 h-100"
             style="background:#fff;box-shadow:0 2px 16px rgba(0,0,0,.07);">
            <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width:48px;height:48px;background:<?= $c['bg'] ?>;border-radius:12px;
                            display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi <?= $c['icon'] ?>" style="color:<?= $c['color'] ?>;font-size:1.2rem;"></i>
                </div>
                <h5 class="mb-0"><?= e($c['title']) ?></h5>
            </div>
            <?php foreach ($c['lines'] as $line): ?>
            <p class="mb-1" style="font-size:.95rem;font-weight:500;"><?= e($line) ?></p>
            <?php endforeach; ?>
            <?php if ($c['note']): ?>
            <p class="mb-0 mt-2" style="font-size:.8rem;color:var(--brand-muted);">
                <i class="bi bi-clock me-1"></i><?= e($c['note']) ?>
            </p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

</div>
</div>
</section>

</main>

<?php require_once 'includes/footer.php'; ?>