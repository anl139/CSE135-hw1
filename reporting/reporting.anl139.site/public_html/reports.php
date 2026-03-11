<?php
require 'auth.php';
require_auth();

// Fetch logs from API
$logs = json_decode(file_get_contents("https://reporting.anl139.site/api/logs"), true);

// Keep only the top 50 logs
$logs = array_slice($logs, 0, 50);

// Prepare chart data from data field
$activityCounts = [];
$pageVisits = [];
$navTiming = [];

foreach ($logs as $log) {
    $data = json_decode($log['data'] ?? '{}', true);
    $activityData = $data['activityData'] ?? $data['activitySummary'] ?? [];
    $activityCounts[] = [
        'clicks' => count($activityData['clicks'] ?? []),
        'scrolls' => count($activityData['scrolls'] ?? []),
        'mouseMoves' => count($activityData['mouseMoves'] ?? []),
        'errors' => count($activityData['errors'] ?? [])
    ];
    if (isset($data['page'])) {
        $page = $data['page'];
    } elseif (isset($data['url'])) {
        $page = parse_url($data['url'], PHP_URL_PATH);
    } else {
        $page = 'Unknown';
    }
    $pageVisits[$page] = ($pageVisits[$page] ?? 0) + 1;

    // NavTiming if exists
    if (isset($data['navTiming'])) {
        $navigationStart = $data['navTiming']['navigationStart'] ?? 0;
        $domContent = ($data['navTiming']['domComplete'] ?? 0) - $navigationStart;
        $loadTime = ($data['navTiming']['loadEventEnd'] ?? $data['navTiming']['totalLoadTime'] ?? 0) - $navigationStart;

        $navTiming[] = [
            'domContentLoaded' => max(0, $domContent),
            'loadTime' => max(0, $loadTime)
        ];
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reports</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        pre { white-space: pre-wrap; word-wrap: break-word; max-width: 600px; }
        table { border-collapse: collapse; margin-bottom: 30px; }
        th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
    </style>
</head>
<body>
<h1>Reports</h1>
<p><a href="/logout.php">Logout</a></p>

<!-- ===== Table with Data as JSON ===== -->
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Session</th>
            <th>Type</th>
            <th>Timestamp</th>
            <th>Summary</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($logs as $index => $log):
            $data = json_decode($log['data'] ?? '{}', true);

            // ===== Activity Summary =====
            $activityData = $data['activityData'] ?? $data['activitySummary'] ?? [];
            $activitySummary = [];
            foreach (['clicks', 'scrolls', 'mouseMoves', 'errors'] as $key) {
                if (!empty($activityData[$key])) {
                    // Show only first 5 items for readability
                    $activitySummary[$key] = array_slice($activityData[$key], 0, 5);
                    if (count($activityData[$key]) > 5) {
                        $activitySummary[$key . '_more'] = '... ' . (count($activityData[$key]) - 5) . ' more';
                    }
                } else {
                    $activitySummary[$key] = [];
                }
            }

            // ===== NavTiming Summary =====
            $navTimingSummary = [];
            if (isset($data['navTiming'])) {
                $navigationStart = $data['navTiming']['navigationStart'] ?? 0;
                $domContent = ($data['navTiming']['domComplete'] ?? 0) - $navigationStart;
                $loadTime = ($data['navTiming']['loadEventEnd'] ?? $data['navTiming']['totalLoadTime'] ?? 0) - $navigationStart;
                $navTimingSummary = [
                    'domContentLoaded' => round(max(0, $domContent), 2),
                    'loadTime' => round(max(0, $loadTime), 2)
                ];
            }

            // ===== Combined Summary =====
            $summary = [
                'page' => $data['page'] ?? $data['url'] ?? 'Unknown',
                'activity' => $activitySummary,
                'navTiming' => $navTimingSummary
            ];
        ?>
        <tr>
            <td><?= $index + 1 ?></td>
            <td><?= htmlspecialchars($log['session_id'] ?? '') ?></td>
            <td><?= htmlspecialchars($log['log_type'] ?? '') ?></td>
            <td><?= htmlspecialchars($log['timestamp'] ?? '') ?></td>
            <td><pre><?= json_encode($summary, JSON_PRETTY_PRINT) ?></pre></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<!-- ===== Activity Chart ===== -->
<canvas id="activityChart" width="600" height="300"></canvas>
<!-- ===== Page Visits Chart ===== -->
<canvas id="pageVisitsChart" width="600" height="300"></canvas>
<!-- ===== NavTiming Chart ===== -->
<canvas id="navTimingChart" width="600" height="300"></canvas>

<script>
const activityCounts = <?= json_encode($activityCounts) ?>;
const pageVisits = <?= json_encode($pageVisits) ?>;
const navTiming = <?= json_encode($navTiming) ?>;

// ===== Activity Chart =====
new Chart(document.getElementById('activityChart'), {
    type: 'bar',
    data: {
        labels: activityCounts.map((_, i) => 'Log ' + (i + 1)),
        datasets: [
            { label: 'Clicks', data: activityCounts.map(a => a.clicks), backgroundColor: 'rgba(75,192,192,0.6)' },
            { label: 'Scrolls', data: activityCounts.map(a => a.scrolls), backgroundColor: 'rgba(153,102,255,0.6)' },
            { label: 'Mouse Moves', data: activityCounts.map(a => a.mouseMoves), backgroundColor: 'rgba(255,159,64,0.6)' },
            { label: 'Errors', data: activityCounts.map(a => a.errors), backgroundColor: 'rgba(255,99,132,0.6)' }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
});

// ===== Page Visits Chart =====
new Chart(document.getElementById('pageVisitsChart'), {
    type: 'pie',
    data: {
        labels: Object.keys(pageVisits),
        datasets: [{
            label: 'Page Visits',
            data: Object.values(pageVisits),
            backgroundColor: ['rgba(75,192,192,0.6)','rgba(153,102,255,0.6)','rgba(255,159,64,0.6)','rgba(255,99,132,0.6)']
        }]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } } }
});

// ===== NavTiming Chart =====
new Chart(document.getElementById('navTimingChart'), {
    type: 'bar',
    data: {
        labels: navTiming.map((_, i) => 'Log ' + (i + 1)),
        datasets: [
            { label: 'DOM Content Loaded (ms)', data: navTiming.map(n => n.domContentLoaded), backgroundColor: 'rgba(75,192,192,0.6)' },
            { label: 'Total Load Time (ms)', data: navTiming.map(n => n.loadTime), backgroundColor: 'rgba(153,102,255,0.6)' }
        ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
});
</script>
</body>
</html>
