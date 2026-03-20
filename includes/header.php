<?php

// Prevent back-button bypass after logout on all pages that include this header
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
}

define('BASE_URL', '/');

$page_title = $page_title ?? 'Tupi Tourism';

$current = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Discover the beautiful tourist spots of Tupi, South Cotabato.">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">

    <title><?= e($page_title) ?> | Tupi Tourism</title>

    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;900&family=DM+Sans:wght@300;400;500;600&display=swap"
          rel="stylesheet">

    <style>
        :root {
            --brand-green:  #1a6b3a;
            --brand-gold:   #c8a951;
            --brand-dark:   #0f2318;
            --brand-light:  #f5f0e8;
            --brand-muted:  #6b7c6e;
            --card-radius:  14px;
            --transition:   0.25s ease;
            --font-display: 'Playfair Display', Georgia, serif;
            --font-body:    'DM Sans', system-ui, sans-serif;
        }
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: var(--font-body);
            background-color: var(--brand-light);
            color: #1c2a1e;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        h1, h2, h3, h4, h5 { font-family: var(--font-display); }
        main { flex: 1; }

        .site-nav {
            background: var(--brand-dark);
            padding: .75rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,.35);
        }
        .site-nav .navbar-brand {
            font-family: var(--font-display);
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--brand-gold) !important;
            letter-spacing: -.02em;
            text-decoration: none;
        }
        .site-nav .navbar-brand span { color: #fff; font-weight: 300; }
        .site-nav .nav-link {
            color: rgba(255,255,255,.75) !important;
            font-weight: 500;
            font-size: .875rem;
            letter-spacing: .04em;
            text-transform: uppercase;
            padding: .5rem 1rem !important;
            border-radius: 6px;
            transition: color var(--transition), background var(--transition);
        }
        .site-nav .nav-link:hover,
        .site-nav .nav-link.active {
            color: #fff !important;
            background: rgba(255,255,255,.08);
        }
        .site-nav .btn-nav-login {
            background: var(--brand-gold);
            color: var(--brand-dark) !important;
            font-weight: 700;
            border-radius: 6px;
            padding: .4rem 1.2rem !important;
        }
        .site-nav .btn-nav-login:hover { background: #e0bc62; }
        .site-nav .navbar-toggler { border-color: rgba(255,255,255,.3); }
        .site-nav .navbar-toggler-icon { filter: invert(1); }

        .site-nav .dropdown-menu {
            background: var(--brand-dark);
            border: 1px solid rgba(255,255,255,.1);
            border-radius: 10px;
            min-width: 180px;
            padding: .5rem;
            margin-top: .5rem;
        }
        .site-nav .dropdown-item {
            color: rgba(255,255,255,.75);
            font-size: .85rem;
            border-radius: 6px;
            padding: .5rem .85rem;
            transition: background var(--transition), color var(--transition);
        }
        .site-nav .dropdown-item:hover {
            background: rgba(255,255,255,.1);
            color: #fff;
        }
        .site-nav .dropdown-divider { border-color: rgba(255,255,255,.1); }

        .btn-brand {
            background: var(--brand-green);
            color: #fff;
            font-weight: 600;
            border: none;
            border-radius: 8px;
            padding: .6rem 1.5rem;
            transition: background var(--transition), transform var(--transition);
        }
        .btn-brand:hover { background: #145530; color: #fff; transform: translateY(-1px); }
        .btn-gold { background: var(--brand-gold); color: var(--brand-dark); font-weight: 700;
                    border: none; border-radius: 8px; padding: .6rem 1.5rem; }
        .btn-gold:hover { background: #e0bc62; color: var(--brand-dark); }

        .location-card {
            border: none;
            border-radius: var(--card-radius);
            overflow: hidden;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,.06);
            transition: transform var(--transition), box-shadow var(--transition);
            height: 100%;
        }
        .location-card:hover { transform: translateY(-4px); box-shadow: 0 8px 30px rgba(0,0,0,.12); }
        .location-card .card-img-top { height: 210px; object-fit: cover; }
        .location-card .card-body    { padding: 1.25rem; }
        .location-card .card-title   {
            font-size: 1.1rem; font-weight: 700; margin-bottom: .4rem;
            color: var(--brand-dark); font-family: var(--font-display);
        }
        .location-card .card-text { font-size: .875rem; color: var(--brand-muted); line-height: 1.6; }
        .badge-category {
            background: var(--brand-light); color: var(--brand-green);
            font-size: .7rem; font-weight: 700; letter-spacing: .06em;
            text-transform: uppercase; border-radius: 4px;
            padding: .25em .6em; border: 1px solid #d4e8da;
        }
        .badge-cost {
            background: #fff8e7; color: #8a6a10; font-size: .8rem;
            font-weight: 600; border-radius: 4px;
            padding: .25em .6em; border: 1px solid #f0dfa0;
        }

        .auth-card {
            border: none; border-radius: 18px;
            box-shadow: 0 4px 40px rgba(0,0,0,.10); background: #fff;
        }
        .form-control, .form-select {
            border-radius: 8px; border-color: #d0d9d2;
            font-size: .925rem; padding: .65rem 1rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: var(--brand-green);
            box-shadow: 0 0 0 3px rgba(26,107,58,.15);
        }
        .form-label { font-weight: 600; font-size: .875rem; color: #2a3e2d; margin-bottom: .4rem; }

        .site-footer {
            background: var(--brand-dark); color: rgba(255,255,255,.6);
            padding: 2rem 0; margin-top: auto; font-size: .85rem;
        }
        .site-footer a { color: var(--brand-gold); text-decoration: none; }

        .alert { border-radius: 10px; font-size: .9rem; }

        .section-eyebrow {
            font-size: .75rem; font-weight: 700; letter-spacing: .12em;
            text-transform: uppercase; color: var(--brand-green);
        }
        .text-brand  { color: var(--brand-green) !important; }
        .text-gold   { color: var(--brand-gold)  !important; }
        .bg-brand    { background: var(--brand-green) !important; }
        .divider-brand {
            border: none; height: 3px; width: 50px;
            background: var(--brand-gold); margin: .75rem 0 1.5rem;
        }
    </style>
</head>
<body>

<nav class="navbar site-nav navbar-expand-lg">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            Tupi <span>Tourism</span>
        </a>

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">

            <!-- Left nav: public links always visible -->
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>"
                       href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'locations.php' ? 'active' : '' ?>"
                       href="locations.php">Destinations</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'helpline.php' ? 'active' : '' ?>"
                       href="helpline.php">Helpline</a>
                </li>

                <!-- Logged-in only nav links -->
                <?php if (is_logged_in()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'trip_planner.php' ? 'active' : '' ?>"
                       href="trip_planner.php">Trip Planner</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'bookings.php' ? 'active' : '' ?>"
                       href="bookings.php">Bookings</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'favorites.php' ? 'active' : '' ?>"
                       href="favorites.php">Favorites</a>
                </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link" href="index.php#contact">Contact</a>
                </li>
            </ul>

            <!-- Right nav -->
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center gap-1">
                <?php if (is_logged_in()): ?>

                    <?php if (is_admin()): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="admin/admin_panel.php"
                           style="color:var(--brand-gold) !important;">
                            <i class="bi bi-shield-lock-fill me-1"></i>Admin
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- User dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle"
                           href="#"
                           id="userDropdown"
                           role="button"
                           data-bs-toggle="dropdown"
                           aria-expanded="false">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= e($_SESSION['username'] ?? 'Account') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end"
                            aria-labelledby="userDropdown">
                            <li>
                                <a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>"
                                   href="dashboard.php">
                                    <i class="bi bi-speedometer2 me-2"></i>Dashboard
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?= basename($_SERVER['PHP_SELF']) === 'edit_account.php' ? 'active' : '' ?>"
                                   href="edit_account.php">
                                    <i class="bi bi-pencil-square me-2"></i>Edit Account
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                                </a>
                            </li>
                        </ul>
                    </li>

                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Register</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn-nav-login" href="login.php">Sign In</a>
                    </li>
                <?php endif; ?>
            </ul>

        </div>
    </div>
</nav>