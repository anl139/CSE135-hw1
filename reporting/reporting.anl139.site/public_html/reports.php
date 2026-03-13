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
$activityCounts = $activityCounts ?? [];
$navTiming = $navTiming ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>

    <link rel="stylesheet" href="/styles/styles.css">

    <!-- Chart.js is required for navChart and activityChart -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- If your exportPDF function uses html2pdf, keep this dependency -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
    <header class="dashboard-header">
        <h1>Dashboard</h1>
    </header>

    <nav class="dashboard-tabs" aria-label="Dashboard sections">
        <?php if (in_array('overview', $userSections, true)): ?>
            <button type="button" class="tab-button active" data-tab="overview">Overview</button>
        <?php endif; ?>

        <?php if (in_array('performance', $userSections, true)): ?>
            <button type="button" class="tab-button" data-tab="performance">Performance</button>
        <?php endif; ?>

        <?php if (in_array('behavioral', $userSections, true)): ?>
            <button type="button" class="tab-button" data-tab="behavioral">Behavioral</button>
        <?php endif; ?>
    </nav>

    <main class="dashboard-main">
        <?php if (in_array('overview', $userSections, true)): ?>
            <section id="overview" class="tab-content active">
                <div class="tab-actions">
                    <button type="button" data-export-pdf="overview">Export PDF</button>
                </div>

                <!-- Keep your existing Overview content here -->
                <div class="panel">
                    <h2>Overview</h2>
                    <p>Your existing overview widgets, tables, and summaries go here.</p>
                </div>
            </section>
        <?php endif; ?>

        <?php if (in_array('performance', $userSections, true)): ?>
            <section id="performance" class="tab-content">
                <div class="tab-actions">
                    <button type="button" data-export-pdf="performance">Export PDF</button>
                </div>

                <div class="panel">
                    <h2>Performance</h2>
                    <canvas id="navChart" height="120"></canvas>
                </div>

                <!-- Keep your existing Performance content here -->
                <div class="panel">
                    <p>Your existing performance tables and metrics go here.</p>
                </div>
            </section>
        <?php endif; ?>

        <?php if (in_array('behavioral', $userSections, true)): ?>
            <section id="behavioral" class="tab-content">
                <div class="tab-actions">
                    <button type="button" data-export-pdf="behavioral">Export PDF</button>
                </div>

                <div class="panel">
                    <h2>Behavioral</h2>
                    <canvas id="activityChart" height="120"></canvas>
                </div>

                <!-- Keep your existing Behavioral content here -->
                <div class="panel">
                    <p>Your existing behavioral analytics go here.</p>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <!-- Safe PHP → JS data handoff -->
    <script type="application/json" id="dashboard-data">
        <?= json_encode(
            [
                'activityCounts' => $activityCounts,
                'navTiming' => $navTiming,
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>
    </script>

    <script src="/js/dashboard.js"></script>
</body>
</html>
</body>
</html>
