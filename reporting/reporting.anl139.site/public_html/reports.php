<?php
require 'auth.php';
require_auth();

$role = $_SESSION['user']['role'] ?? 'viewer';
$userSections = ['overview', 'performance', 'behavioral']; // all sections exist
$analystComments = [
    'overview' => "Displays key session activity, errors, and technology per user.",
    'performance' => "Web Vitals (LCP, CLS, INP) and load times per page.",
    'behavioral' => "User activity summary: clicks, mouse movements, keypresses, and idle times."
];

// --- Load logs based on role ---
if ($role === 'viewer') {
    // Viewer: only static saved overview
    $file = __DIR__ . '/saved_reports/overview.json';
    if (file_exists($file)) {
        $logs = json_decode(file_get_contents($file), true) ?? [];
    } else {
        $logs = [];
    }
} else {
    // Analyst / Super Admin: live logs
    $logsRaw = json_decode(file_get_contents("https://reporting.anl139.site/api/logs"), true) ?? [];
    $logsRaw = array_slice($logsRaw, 0, 50);

    $logs = array_map(function($log) {
        $data = json_decode($log['data'] ?? '{}', true) ?? [];
        $activity = $data['activitySummary'] ?? [];
        $vitals = $data['vitals'] ?? [];
        $tech = $data['technographics'] ?? [];

        $sliceData = fn($arr) => array_slice($arr ?? [], 0, 3);

        $clicks = array_map(fn($c) => "({$c['x']},{$c['y']})@" . (!empty($c['t']) ? date('H:i:s', $c['t']/1000) : '-'), $sliceData($activity['clicks'] ?? []));
        $mouse = array_map(fn($m) => "({$m['x']},{$m['y']})@" . (!empty($m['t']) ? date('H:i:s', $m['t']/1000) : '-'), $sliceData($activity['mouseMoves'] ?? []));
        $keys = array_map(fn($k) => ($k['key'] ?? '-') . "@" . (!empty($k['t']) ? date('H:i:s', $k['t']/1000) : '-'), $sliceData($activity['keys'] ?? []));
        $errorsList = array_map(fn($e) => ($e['message'] ?? '-') . "@" . (!empty($e['t']) ? date('H:i:s', $e['t']/1000) : '-'), array_slice($activity['errors'] ?? [], 0, 3));

        $techSummary = '';
        if (!empty($tech)) {
            $cores = $tech['cores'] ?? '-';
            $memory = $tech['memory'] ?? '-';
            $network = $tech['network']['effectiveType'] ?? '-';
            $screen = ($tech['screenWidth'] ?? '-') . "x" . ($tech['screenHeight'] ?? '-');
            $techSummary = "$cores cores, $memory GB, $network, $screen";
        }

        $perf = isset($data['navTiming']) ? [
            'page' => $data['page'] ?? 'Unknown',
            'lcp' => $vitals['lcp'] ?? null,
            'cls' => $vitals['cls'] ?? null,
            'inp' => $vitals['inp'] ?? null,
            'domContentLoaded' => $data['navTiming']['domInteractive'] ?? null,
            'loadTime' => $data['navTiming']['totalLoadTime'] ?? null
        ] : [];

        $activityCounts = [
            'clicks' => count($activity['clicks'] ?? []),
            'scrolls' => count($activity['scrolls'] ?? []),
            'mouseMoves' => count($activity['mouseMoves'] ?? []),
            'keys' => count($activity['keys'] ?? []),
            'idleTimes' => count($activity['idleTimes'] ?? []),
            'errors' => $data['errorCount'] ?? 0
        ];

        return [
            'raw' => $log,
            'clicks' => $clicks,
            'mouse' => $mouse,
            'keys' => $keys,
            'errorsList' => $errorsList,
            'tech' => $techSummary,
            'perf' => $perf,
            'activityCounts' => $activityCounts
        ];
    }, $logsRaw);
}

$activityCountsChart = array_map(fn($l) => $l['activityCounts'], $logs);
$navTimingChart = array_values(array_filter(array_map(fn($l) => $l['perf'], $logs)));

$analystComments = [
    'overview' => "Displays key session activity, errors, and technology per user.",
    'performance' => "Web Vitals (LCP, CLS, INP) and load times per page.",
    'behavioral' => "User activity summary: clicks, mouse movements, keypresses, and idle times."
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
        <?php foreach ($allSections as $sec): ?>
            <?php if (in_array($sec, $userSections, true)): ?>
                <a href="#<?= $sec ?>" class="<?= $sec==='overview'?'active':'' ?>" data-tab="<?= $sec ?>"><?= ucfirst($sec) ?></a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>

    <main class="main-content">

    <!-- OVERVIEW -->
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
                    <th>Page</th>
                    <th>Errors</th>
                    <th>Clicks</th>
                    <th>Scrolls</th>
                    <th>MouseMoves</th>
                    <th>Keys</th>
                    <th>Idle</th>
                    <th>Technology</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach (array_slice($logs, 0, 10) as $i => $l): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($l['raw']['session_id'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($l['perf']['page'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($l['activityCounts']['errors'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($l['activityCounts']['clicks'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($l['activityCounts']['scrolls'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($l['activityCounts']['mouseMoves'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($l['activityCounts']['keys'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($l['activityCounts']['idleTimes'] ?? 0) ?></td>
                    <td title="<?= htmlspecialchars(json_encode($l['data']['technographics'] ?? [])) ?>">
                        <?= htmlspecialchars($l['tech']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- PERFORMANCE -->
    <?php if (in_array('performance', $userSections, true)): ?>
    <div id="performance" class="tab-content" style="display:none;">
        <h2>Performance</h2>
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

    <!-- BEHAVIORAL -->
<?php if (in_array('behavioral', $userSections, true)): ?>
<div id="behavioral" class="tab-content" style="display:none;">
    <h2>Behavioral</h2>
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
                <th>Errors</th> <!-- NEW COLUMN -->
            </tr>
        </thead>
        <tbody>
        <?php foreach (array_slice($logs, 0, 10) as $i => $l): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars(implode(", ", $l['mouse'])) ?></td>
                <td><?= htmlspecialchars(implode(", ", $l['clicks'])) ?></td>
                <td><?= htmlspecialchars(implode(", ", $l['keys'])) ?></td>
                <td><?= htmlspecialchars(implode(", ", $l['errorsList'] ?? [])) ?></td> <!-- NEW DATA -->
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

    </main>
</div>
<script>
    window.DASHBOARD_USER = {
        role: "<?= $role ?>",
        displayName: "<?= htmlspecialchars($_SESSION['user']['displayName']) ?>"
    };
</script>
<script type="application/json" id="dashboard-data">
<?= json_encode([
    'activityCounts' => array_values($activityCountsChart),
    'navTiming' => $navTimingChart
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
</script>

<script src="/js/dashboard.js"></script>
</body>
</html>
