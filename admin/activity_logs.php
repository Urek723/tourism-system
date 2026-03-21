<?php
/**
 * admin/activity_logs.php — Activity Log Viewer + Visual Analytics
 */

require_once '../db.php';
require_once '../functions.php';

start_secure_session();
require_admin();
if (empty($_SESSION['admin_session'])) {
    destroy_session();
    redirect('admin_login.php');
}

$page_title = 'Activity Logs';

$page     = max(1, clean_int($_GET['page'] ?? 1));
$per_page = 30;
$offset   = ($page - 1) * $per_page;

$filter_action = clean_string($_GET['action'] ?? '', 50);

$logs  = [];
$total = 0;

try {
    $db = get_db();

    if (!empty($filter_action)) {
        $like = '%' . $filter_action . '%';
        $cStmt = $db->prepare(
            'SELECT COUNT(*) FROM activity_logs WHERE action LIKE ?'
        );
        $cStmt->execute([$like]);
        $total = (int)$cStmt->fetchColumn();

        $stmt = $db->prepare(
            'SELECT a.id, a.action, a.created_at, u.username
             FROM   activity_logs a
             JOIN   users         u ON u.id = a.user_id
             WHERE  a.action LIKE ?
             ORDER  BY a.created_at DESC
             LIMIT  ? OFFSET ?'
        );
        $stmt->execute([$like, $per_page, $offset]);
    } else {
        $total = (int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn();
        $stmt  = $db->prepare(
            'SELECT a.id, a.action, a.created_at, u.username
             FROM   activity_logs a
             JOIN   users         u ON u.id = a.user_id
             ORDER  BY a.created_at DESC
             LIMIT  ? OFFSET ?'
        );
        $stmt->execute([$per_page, $offset]);
    }
    $logs = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('[ADMIN ACTIVITY LOGS] ' . $e->getMessage());
}

$total_pages = (int)ceil($total / $per_page);

// ── Chart data queries (last 30 days) ─────────────────────────────────────

// Pie: distribution by action
$dist_labels = [];
$dist_data   = [];
try {
    $stmt = $db->prepare(
        'SELECT action, COUNT(*) AS n
         FROM   activity_logs
         WHERE  created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP  BY action
         ORDER  BY n DESC'
    );
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $dist_labels[] = $row['action'];
        $dist_data[]   = (int) $row['n'];
    }
} catch (PDOException $e) {
    error_log('[ACTIVITY LOGS — DIST] ' . $e->getMessage());
}

// Line: activity per day
$time_labels = [];
$time_data   = [];
try {
    $stmt = $db->prepare(
        'SELECT DATE(created_at) AS day, COUNT(*) AS n
         FROM   activity_logs
         WHERE  created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP  BY DATE(created_at)
         ORDER  BY day ASC'
    );
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $time_labels[] = $row['day'];
        $time_data[]   = (int) $row['n'];
    }
} catch (PDOException $e) {
    error_log('[ACTIVITY LOGS — TIME] ' . $e->getMessage());
}

// Bar: top 10 most active users
$user_labels = [];
$user_data   = [];
try {
    $stmt = $db->prepare(
        'SELECT u.username, COUNT(a.id) AS n
         FROM   activity_logs a
         JOIN   users         u ON u.id = a.user_id
         WHERE  a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
         GROUP  BY a.user_id
         ORDER  BY n DESC
         LIMIT  10'
    );
    $stmt->execute();
    foreach ($stmt->fetchAll() as $row) {
        $user_labels[] = $row['username'];
        $user_data[]   = (int) $row['n'];
    }
} catch (PDOException $e) {
    error_log('[ACTIVITY LOGS — USERS] ' . $e->getMessage());
}

$json_dist_labels = json_encode($dist_labels, JSON_HEX_TAG | JSON_HEX_AMP);
$json_dist_data   = json_encode($dist_data,   JSON_HEX_TAG | JSON_HEX_AMP);
$json_time_labels = json_encode($time_labels, JSON_HEX_TAG | JSON_HEX_AMP);
$json_time_data   = json_encode($time_data,   JSON_HEX_TAG | JSON_HEX_AMP);
$json_user_labels = json_encode($user_labels, JSON_HEX_TAG | JSON_HEX_AMP);
$json_user_data   = json_encode($user_data,   JSON_HEX_TAG | JSON_HEX_AMP);

function action_badge(string $action): string
{
    if (str_starts_with($action, 'admin_')) return 'background:#fce8e8;color:#c0392b;';
    if (str_contains($action, 'login'))     return 'background:#d4e8da;color:#1a6b3a;';
    if (str_contains($action, 'logout'))    return 'background:#e8ede9;color:#4a5a4e;';
    if (str_contains($action, 'deleted'))   return 'background:#fff3cd;color:#856404;';
    return 'background:#e8f0fc;color:#1a4fa0;';
}

require_once 'admin_header.php';
?>

<!-- ── Charts (last 30 days) ─────────────────────────────────────────────── -->
<style>
.chart-card {
    background: #fff;
    border-radius: 12px;
    padding: 1.4rem 1.5rem;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
    height: 100%;
}
.chart-card-title {
    font-size: .72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--admin-muted);
    margin-bottom: 1.1rem;
}
</style>

<div class="row g-3 mb-4">

    <!-- Pie -->
    <div class="col-lg-4">
        <div class="chart-card">
            <div class="chart-card-title">
                <i class="bi bi-pie-chart-fill me-1" style="color:#c0392b;"></i>
                Action Distribution — Last 30 Days
            </div>
            <?php if (empty($dist_data)): ?>
                <p class="text-muted" style="font-size:.82rem;">No data.</p>
            <?php else: ?>
                <canvas id="pieChart" style="max-height:240px;"></canvas>
            <?php endif; ?>
        </div>
    </div>

    <!-- Line -->
    <div class="col-lg-8">
        <div class="chart-card">
            <div class="chart-card-title">
                <i class="bi bi-graph-up me-1" style="color:#c0392b;"></i>
                Activity Over Time — Last 30 Days
            </div>
            <?php if (empty($time_data)): ?>
                <p class="text-muted" style="font-size:.82rem;">No data.</p>
            <?php else: ?>
                <canvas id="lineChart" style="max-height:240px;"></canvas>
            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Bar -->
<div class="stat-card p-4 mb-4">
    <div class="chart-card-title">
        <i class="bi bi-bar-chart-fill me-1" style="color:#c0392b;"></i>
        Most Active Users — Last 30 Days (Top 10)
    </div>
    <?php if (empty($user_data)): ?>
        <p class="text-muted" style="font-size:.82rem;">No data.</p>
    <?php else: ?>
        <canvas id="barChart" style="max-height:220px;"></canvas>
    <?php endif; ?>
</div>

<!-- ── Filter ─────────────────────────────────────────────────────────────── -->
<form method="GET" action="activity_logs.php" class="d-flex gap-2 mb-3">
    <input type="text" name="action" class="form-control form-control-sm"
           placeholder="Filter by action (e.g. login, admin_…)"
           value="<?= eAttr($filter_action) ?>" maxlength="50" style="width:260px;">
    <button type="submit" class="btn btn-sm btn-outline-secondary">Filter</button>
    <?php if ($filter_action): ?>
    <a href="activity_logs.php" class="btn btn-sm btn-outline-secondary">Clear</a>
    <?php endif; ?>
    <span class="ms-auto align-self-center" style="font-size:.8rem;color:var(--admin-muted);">
        <?= number_format($total) ?> record<?= $total !== 1 ? 's' : '' ?>
    </span>
</form>

<!-- ── Table ──────────────────────────────────────────────────────────────── -->
<div class="stat-card p-0">
<div class="table-responsive">
<table class="table table-hover align-middle mb-0 admin-table">
    <thead>
        <tr>
            <th class="px-3">#</th>
            <th>User</th>
            <th>Action</th>
            <th>Timestamp</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($logs as $log): ?>
    <tr>
        <td class="px-3" style="color:var(--admin-muted);font-size:.78rem;">
            <?= (int)$log['id'] ?>
        </td>
        <td class="fw-semibold"><?= e($log['username']) ?></td>
        <td>
            <span style="font-family:monospace;font-size:.8rem;padding:.2em .6em;
                         border-radius:4px;<?= action_badge($log['action']) ?>">
                <?= e($log['action']) ?>
            </span>
        </td>
        <td style="color:var(--admin-muted);font-size:.82rem;">
            <?= e(date('M j, Y g:i:s A', strtotime($log['created_at']))) ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
</div>

<!-- ── Pagination ─────────────────────────────────────────────────────────── -->
<?php if ($total_pages > 1): ?>
<div class="d-flex justify-content-center mt-4 gap-1 flex-wrap">
    <?php if ($page > 1): ?>
    <a href="?page=<?= $page-1 ?><?= $filter_action ? '&action='.urlencode($filter_action) : '' ?>"
       class="btn btn-sm btn-outline-secondary">« Prev</a>
    <?php endif; ?>

    <?php
    $start = max(1, $page - 2);
    $end   = min($total_pages, $page + 2);
    for ($p = $start; $p <= $end; $p++):
    ?>
    <a href="?page=<?= $p ?><?= $filter_action ? '&action='.urlencode($filter_action) : '' ?>"
       class="btn btn-sm <?= $p === $page ? '' : 'btn-outline-secondary' ?>"
       style="<?= $p === $page ? 'background:#c0392b;color:#fff;' : '' ?>">
        <?= $p ?>
    </a>
    <?php endfor; ?>

    <?php if ($page < $total_pages): ?>
    <a href="?page=<?= $page+1 ?><?= $filter_action ? '&action='.urlencode($filter_action) : '' ?>"
       class="btn btn-sm btn-outline-secondary">Next »</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Chart.js ───────────────────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"
        crossorigin="anonymous"></script>
<script>
(function () {
    'use strict';

    Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
    Chart.defaults.font.size   = 11;
    Chart.defaults.color       = '#8892a4';

    var COLORS = [
        '#c0392b','#1a4fa0','#1a6b3a','#856404',
        '#8b1a8b','#e67e22','#16a085','#7f8c8d',
        '#2980b9','#d35400','#27ae60','#8e44ad',
    ];

    // Pie
    var pieEl = document.getElementById('pieChart');
    if (pieEl) {
        new Chart(pieEl, {
            type: 'pie',
            data: {
                labels:   <?= $json_dist_labels ?>,
                datasets: [{
                    data:            <?= $json_dist_data ?>,
                    backgroundColor: COLORS.slice(0, <?= count($dist_data) ?>),
                    borderWidth:     2,
                    borderColor:     '#fff',
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 10, boxWidth: 11, font: { size: 10 } }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                var pct   = total > 0 ? ((ctx.parsed / total) * 100).toFixed(1) : 0;
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Line
    var lineEl = document.getElementById('lineChart');
    if (lineEl) {
        new Chart(lineEl, {
            type: 'line',
            data: {
                labels:   <?= $json_time_labels ?>,
                datasets: [{
                    label:           'Activities',
                    data:            <?= $json_time_data ?>,
                    borderColor:     '#c0392b',
                    backgroundColor: 'rgba(192,57,43,.08)',
                    borderWidth:     2,
                    pointRadius:     3,
                    pointHoverRadius:5,
                    fill:            true,
                    tension:         0.35,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { maxTicksLimit: 10, maxRotation: 0 }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,.05)' },
                        ticks: { stepSize: 1, precision: 0 }
                    }
                }
            }
        });
    }

    // Bar
    var barEl = document.getElementById('barChart');
    if (barEl) {
        new Chart(barEl, {
            type: 'bar',
            data: {
                labels:   <?= $json_user_labels ?>,
                datasets: [{
                    label:           'Actions',
                    data:            <?= $json_user_data ?>,
                    backgroundColor: <?= $json_user_labels ?>.map(function (_, i) {
                        return COLORS[i % COLORS.length];
                    }),
                    borderRadius:    5,
                    borderSkipped:   false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                return ' ' + ctx.parsed.y + ' actions';
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,.05)' },
                        ticks: { stepSize: 1, precision: 0 }
                    }
                }
            }
        });
    }

}());
</script>

<?php require_once 'admin_footer.php'; ?>