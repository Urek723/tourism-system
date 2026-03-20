<?php
// Prevent back-button bypass after logout
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: Sat, 01 Jan 2000 00:00:00 GMT');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title ?? 'Admin') ?> | Tupi Admin</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          crossorigin="anonymous">
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --admin-dark:  #1a1f2e;
            --admin-panel: #242938;
            --admin-red:   #c0392b;
            --admin-accent:#e74c3c;
            --admin-muted: #8892a4;
            --admin-border:#2e3547;
        }
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', system-ui, sans-serif;
            font-size: .9rem;
        }
        .admin-sidebar {
            width: 240px;
            min-height: 100vh;
            background: var(--admin-dark);
            position: fixed;
            top: 0; left: 0;
            display: flex;
            flex-direction: column;
            z-index: 100;
        }
        .admin-brand {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--admin-border);
            color: #fff;
            font-weight: 800;
            font-size: 1rem;
            letter-spacing: -.01em;
        }
        .admin-brand span { color: var(--admin-red); }
        .admin-nav { padding: 1rem 0; flex: 1; }
        .nav-section-label {
            padding: .5rem 1.5rem .25rem;
            font-size: .68rem;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: var(--admin-muted);
        }
        .admin-nav-link {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: .55rem 1.5rem;
            color: rgba(255,255,255,.65);
            text-decoration: none;
            font-size: .85rem;
            font-weight: 500;
            transition: background .15s, color .15s;
            border-left: 3px solid transparent;
        }
        .admin-nav-link:hover,
        .admin-nav-link.active {
            background: rgba(255,255,255,.05);
            color: #fff;
            border-left-color: var(--admin-red);
        }
        .admin-nav-link i { font-size: 1rem; width: 18px; }
        .admin-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--admin-border);
        }
        .logout-btn {
            display: flex; align-items: center; gap: .5rem;
            color: rgba(255,255,255,.5);
            text-decoration: none;
            font-size: .82rem;
            transition: color .15s;
        }
        .logout-btn:hover { color: var(--admin-red); }
        .admin-main { margin-left: 240px; min-height: 100vh; }
        .admin-topbar {
            background: #fff;
            padding: .85rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .admin-topbar h1 {
            font-size: 1.1rem;
            font-weight: 700;
            margin: 0;
            color: #1a1f2e;
        }
        .admin-content { padding: 1.75rem 1.5rem; }
        .admin-table th {
            background: #f8fafc;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--admin-muted);
            border-bottom: 2px solid #e2e8f0;
        }
        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }
        .alert { border-radius: 10px; font-size: .875rem; }
        .role-admin { background:#fce8e8;color:#c0392b; }
        .role-user  { background:#e8f0fc;color:#1a4fa0; }
        @media (max-width: 768px) {
            .admin-sidebar { display: none; }
            .admin-main    { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<aside class="admin-sidebar">
    <div class="admin-brand">
        <i class="bi bi-shield-lock-fill me-2" style="color:var(--admin-red);"></i>
        Tupi <span>Admin</span>
    </div>

    <nav class="admin-nav">
        <div class="nav-section-label">Overview</div>
        <a href="<?= BASE_URL ?>admin/admin_panel.php"
           class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) === 'admin_panel.php' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-section-label mt-2">Manage</div>
        <a href="<?= BASE_URL ?>admin/manage_users.php"
           class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) === 'manage_users.php' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i> Users
        </a>
        <a href="<?= BASE_URL ?>admin/manage_locations.php"
           class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) === 'manage_locations.php' ? 'active' : '' ?>">
            <i class="bi bi-geo-alt-fill"></i> Locations
        </a>
        <a href="<?= BASE_URL ?>admin/manage_comments.php"
           class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) === 'manage_comments.php' ? 'active' : '' ?>">
            <i class="bi bi-chat-dots-fill"></i> Comments
            <?php
            try {
                $db   = get_db();
                $stmt = $db->prepare("SELECT COUNT(*) FROM comments WHERE status = 'pending'");
                $stmt->execute();
                $pending = (int)$stmt->fetchColumn();
                if ($pending > 0) {
                    echo '<span class="badge bg-danger ms-auto">' . $pending . '</span>';
                }
            } catch (Exception $e) {}
            ?>
        </a>
        <a href="<?= BASE_URL ?>admin/manage_bookings.php"
           class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) === 'manage_bookings.php' ? 'active' : '' ?>">
            <i class="bi bi-calendar-check-fill"></i> Bookings
        </a>
        <a href="<?= BASE_URL ?>admin/activity_logs.php"
           class="admin-nav-link <?= basename($_SERVER['PHP_SELF']) === 'activity_logs.php' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Activity Logs
        </a>

        <div class="nav-section-label mt-2">Site</div>
        <a href="<?= BASE_URL ?>index.php" class="admin-nav-link" target="_blank">
            <i class="bi bi-box-arrow-up-right"></i> View Site
        </a>
    </nav>

    <div class="admin-footer">
        <div style="color:rgba(255,255,255,.4);font-size:.75rem;margin-bottom:.5rem;">
            Signed in as <strong style="color:#fff;"><?= e($_SESSION['username'] ?? '') ?></strong>
        </div>
        <a href="<?= BASE_URL ?>admin/admin_logout.php" class="logout-btn">
            <i class="bi bi-box-arrow-right"></i> Sign Out
        </a>
    </div>
</aside>

<!-- Main -->
<div class="admin-main">
    <div class="admin-topbar">
        <h1><?= e($page_title ?? 'Admin Panel') ?></h1>
        <div style="font-size:.78rem;color:var(--admin-muted);">
            <i class="bi bi-clock me-1"></i>
            <?= date('D, M j Y — g:i A') ?>
        </div>
    </div>
    <div class="admin-content">