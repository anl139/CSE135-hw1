<?php
require 'auth.php';
require_auth();

$allSections = ['overview', 'performance', 'behavioral'];

$role = $_SESSION['user']['role'] ?? 'viewer';
$userSections = $_SESSION['user']['allowed_sections'] ?? [];

if ($role === 'super_admin') {
    $userSections = $allSections;
} elseif ($role === 'viewer') {
    $userSections = ['overview'];
}

$users = load_users();

$logsRaw = json_decode(file_get_contents("https://reporting.anl139.site/api/logs"), true) ?? [];
$logsRaw = array_slice($logsRaw, 0, 50);

$logs = array_map(function($log) {
    $data = json_decode($log['data'] ?? '{}', true) ?? [];
    $activity = $data['activitySummary'] ?? [];
    $vitals = $data['vitals'] ?? [];
    $tech = $data['technographics'] ?? [];

    $sliceData = fn($arr) => array_slice($arr ?? [], 0, 3);

    $clicks = array_map(
        fn($c) => "({$c['x']},{$c['y']})@" . (!empty($c['t']) ? date('H:i:s', $c['t'] / 1000) : '-'),
        $sliceData($activity['clicks'] ?? [])
    );

    $mouse = array_map(
        fn($m) => "({$m['x']},{$m['y']})@" . (!empty($m['t']) ? date('H:i:s', $m['t'] / 1000) : '-'),
        $sliceData($activity['mouseMoves'] ?? [])
    );

    $keys = array_map(
        fn($k) => ($k['key'] ?? '') . "@" . (!empty($k['t']) ? date('H:i:s', $k['t'] / 1000) : '-'),
        $sliceData($activity['keys'] ?? [])
    );

    $techSummary = '';
    if (!empty($tech)) {
        $cores = $tech['cores'] ?? '-';
        $memory = $tech['memory'] ?? '-';
        $network = $tech['network']['effectiveType'] ?? '-';
        $screen = ($tech['screenWidth'] ?? '-') . "x" . ($tech['screenHeight'] ?? '-');
        $techSummary = "$cores cores, $memory GB, $network, $screen";
    }

    $perf = [];
    if (isset($data['navTiming'])) {
        $perf = [
            'page' => $data['page'] ?? (parse_url($data['url'] ?? '', PHP_URL_PATH) ?? 'Unknown'),
            'lcp' => $vitals['lcp'] ?? null,
            'cls' => $vitals['cls'] ?? null,
            'inp' => $vitals['inp'] ?? null,
            'domContentLoaded' => $data['navTiming']['domInteractive'] ?? null,
            'loadTime' => $data['navTiming']['totalLoadTime'] ?? null
        ];
    }

    $activityCounts = [
        'clicks' => count($activity['clicks'] ?? []),
        'scrolls' => count($activity['scrolls'] ?? []),
        'mouseMoves' => count($activity['mouseMoves'] ?? []),
        'errors' => $data['errorCount'] ?? 0
    ];

    return [
        'raw' => $log,
        'data' => $data,
        'clicks' => $clicks,
        'mouse' => $mouse,
        'keys' => $keys,
        'tech' => $techSummary,
        'perf' => $perf,
        'activityCounts' => $activityCounts
    ];
}, $logsRaw);

$activityCountsChart = array_map(fn($l) => $l['activityCounts'], $logs);
$navTimingChart = array_values(array_filter(array_map(fn($l) => $l['perf'], $logs)));

$analystComments = [
    'overview' => "Displays the first 10 log entries in readable format.",
    'performance' => "Page load performance is under 500ms on average. Monitor LCP spikes.",
    'behavioral' => "Users mainly interact via mouse movements and occasional clicks."
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Analytics Dashboard</title>
<link rel="stylesheet" href="/styles/dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
<div class="dashboard-layout">
    <header class="dashboard-header">
        <h1>Analytics Dashboard</h1>
        <span>User: <?= htmlspecialchars($_SESSION['user']['displayName']) ?> (<?= htmlspecialchars($role) ?>)</span>
        <?php if ($role === 'super_admin'): ?>
            <a class="top-link" href="/admin.php">User Admin</a>
        <?php endif; ?>
        <button type="button" id="logoutBtn">Logout</button>
    </header>

    <nav class="sidebar">
        <?php if (in_array('overview', $userSections, true)): ?>
            <a href="#overview" class="active" data-tab="overview">Overview</a>
        <?php endif; ?>
        <?php if (in_array('performance', $userSections, true)): ?>
            <a href="#performance" data-tab="performance">Performance</a>
        <?php endif; ?>
        <?php if (in_array('behavioral', $userSections, true)): ?>
            <a href="#behavioral" data-tab="behavioral">Behavioral</a>
        <?php endif; ?>
    </nav>

    <main class="main-content">
        <?php if (in_array('overview', $userSections, true)): ?>
        <div id="overview" class="tab-content">
            <h2>Overview</h2>
            <button type="button" data-export-pdf="overview">Export as PDF</button>
            <div class="comments"><?= htmlspecialchars($analystComments['overview']) ?></div>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Session</th>
                        <th>Type</th>
                        <th>Timestamp</th>
                        <th>Mouse</th>
                        <th>Clicks</th>
                        <th>Keys</th>
                        <th>Errors</th>
                        <th>Technographics</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($logs, 0, 10) as $i => $l): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($l['raw']['session_id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($l['raw']['log_type'] ?? '') ?></td>
                        <td><?= htmlspecialchars($l['raw']['timestamp'] ?? '') ?></td>
                        <td><?= htmlspecialchars(implode(", ", $l['mouse'])) ?></td>
                        <td><?= htmlspecialchars(implode(", ", $l['clicks'])) ?></td>
                        <td><?= htmlspecialchars(implode(", ", $l['keys'])) ?></td>
                        <td><?= htmlspecialchars((string)($l['activityCounts']['errors'] ?? 0)) ?></td>
                        <td><?= htmlspecialchars($l['tech']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (in_array('performance', $userSections, true)): ?>
        <div id="performance" class="tab-content" style="display:none;">
            <h2>Performance Reports</h2>
            <button type="button" data-export-pdf="performance">Export as PDF</button>
            <div class="comments"><?= htmlspecialchars($analystComments['performance']) ?></div>
            <canvas id="navChart"></canvas>

            <table>
                <thead>
                    <tr>
                        <th>Page</th>
                        <th>LCP</th>
                        <th>CLS</th>
                        <th>INP</th>
                        <th>DOM Interactive</th>
                        <th>Total Load Time</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($navTimingChart as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['page'] ?? '-') ?></td>
                        <td><?= htmlspecialchars((string)($p['lcp'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string)($p['cls'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string)($p['inp'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string)($p['domContentLoaded'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string)($p['loadTime'] ?? '-')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if (in_array('behavioral', $userSections, true)): ?>
        <div id="behavioral" class="tab-content" style="display:none;">
            <h2>Behavioral Reports</h2>
            <button type="button" data-export-pdf="behavioral">Export as PDF</button>
            <div class="comments"><?= htmlspecialchars($analystComments['behavioral']) ?></div>
            <canvas id="activityChart"></canvas>

            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Mouse</th>
                        <th>Clicks</th>
                        <th>Keys</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (array_slice($logs, 0, 10) as $i => $l): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars(implode(", ", $l['mouse'])) ?></td>
                        <td><?= htmlspecialchars(implode(", ", $l['clicks'])) ?></td>
                        <td><?= htmlspecialchars(implode(", ", $l['keys'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>

<script type="application/json" id="dashboard-data">
<?= json_encode([
    'activityCounts' => array_values($activityCountsChart),
    'navTiming' => $navTimingChart
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
</script>

<script src="/js/dashboard.js"></script>
</body>
</html>
</body>
</html>
