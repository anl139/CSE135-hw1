<?php
declare(strict_types=1);

require 'auth.php';
require_auth();

/**
 * Escape output for safe HTML rendering.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Safe JSON decode with fallback.
 */
function safe_json_decode(string $json, mixed $default = []): mixed
{
    $decoded = json_decode($json, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $default;
}

/**
 * Safe JSON encode for embedding data in HTML.
 */
function safe_json_encode(mixed $value): string
{
    $json = json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );

    return $json !== false ? $json : '{}';
}

/**
 * Fetch remote JSON with a timeout and graceful fallback.
 */
function fetch_json_from_url(string $url, int $timeoutSeconds = 5): array
{
    $context = stream_context_create([
        'http' => [
            'method'  => 'GET',
            'timeout' => $timeoutSeconds,
            'header'  => "Accept: application/json\r\nUser-Agent: PHP Dashboard\r\n",
        ],
    ]);

    $response = @file_get_contents($url, false, $context);
    if ($response === false || $response === '') {
        return [];
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Return the first N items from an array.
 */
function take_first(array $items, int $limit = 3): array
{
    return array_slice($items, 0, $limit);
}

/**
 * Format a timestamp in milliseconds into H:i:s.
 */
function format_ms_timestamp(mixed $timestamp): string
{
    if (!is_numeric($timestamp) || (int)$timestamp <= 0) {
        return '-';
    }

    return date('H:i:s', (int) floor(((int) $timestamp) / 1000));
}

/**
 * Build readable preview strings for click/mouse/key activity.
 */
function format_activity_preview(array $items, string $type): array
{
    $preview = [];

    foreach (take_first($items, 3) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $time = format_ms_timestamp($item['t'] ?? null);

        if ($type === 'point') {
            $x = $item['x'] ?? '?';
            $y = $item['y'] ?? '?';
            $preview[] = "({$x},{$y})@{$time}";
            continue;
        }

        if ($type === 'key') {
            $key = $item['key'] ?? '';
            $preview[] = "{$key}@{$time}";
        }
    }

    return $preview;
}

/**
 * Build a compact tech summary string.
 */
function build_tech_summary(array $tech): string
{
    if (empty($tech)) {
        return '';
    }

    $cores   = $tech['cores'] ?? '-';
    $memory  = $tech['memory'] ?? '-';
    $network = $tech['network']['effectiveType'] ?? '-';
    $screenW = $tech['screenWidth'] ?? '-';
    $screenH = $tech['screenHeight'] ?? '-';

    return "{$cores} cores, {$memory} GB, {$network}, {$screenW}x{$screenH}";
}

/**
 * Build performance payload if nav timing exists.
 */
function build_perf_data(array $data, array $vitals): array
{
    if (!isset($data['navTiming']) || !is_array($data['navTiming'])) {
        return [];
    }

    $page = $data['page'] ?? '';
    if ($page === '') {
        $page = parse_url((string) ($data['url'] ?? ''), PHP_URL_PATH) ?: 'Unknown';
    }

    return [
        'page'            => $page,
        'lcp'             => $vitals['lcp'] ?? null,
        'cls'             => $vitals['cls'] ?? null,
        'inp'             => $vitals['inp'] ?? null,
        'domContentLoaded'=> $data['navTiming']['domInteractive'] ?? null,
        'loadTime'        => $data['navTiming']['totalLoadTime'] ?? null,
    ];
}

/**
 * Normalize a single log record into a dashboard-friendly structure.
 */
function normalize_log_entry(array $log): array
{
    $data = [];
    if (isset($log['data'])) {
        if (is_string($log['data'])) {
            $data = safe_json_decode($log['data'], []);
        } elseif (is_array($log['data'])) {
            $data = $log['data'];
        }
    }

    if (!is_array($data)) {
        $data = [];
    }

    $activity = is_array($data['activitySummary'] ?? null) ? $data['activitySummary'] : [];
    $vitals   = is_array($data['vitals'] ?? null) ? $data['vitals'] : [];
    $tech     = is_array($data['technographics'] ?? null) ? $data['technographics'] : [];

    $clicks = format_activity_preview(is_array($activity['clicks'] ?? null) ? $activity['clicks'] : [], 'point');
    $mouse  = format_activity_preview(is_array($activity['mouseMoves'] ?? null) ? $activity['mouseMoves'] : [], 'point');
    $keys   = format_activity_preview(is_array($activity['keys'] ?? null) ? $activity['keys'] : [], 'key');

    return [
        'raw' => $log,
        'data' => $data,
        'clicks' => $clicks,
        'mouse' => $mouse,
        'keys' => $keys,
        'tech' => build_tech_summary($tech),
        'perf' => build_perf_data($data, $vitals),
        'activityCounts' => [
            'clicks'    => is_array($activity['clicks'] ?? null) ? count($activity['clicks']) : 0,
            'scrolls'   => is_array($activity['scrolls'] ?? null) ? count($activity['scrolls']) : 0,
            'mouseMoves'=> is_array($activity['mouseMoves'] ?? null) ? count($activity['mouseMoves']) : 0,
            'errors'    => (int) ($data['errorCount'] ?? 0),
        ],
    ];
}

$allSections = ['overview', 'performance', 'behavioral'];

$role = $_SESSION['user']['role'] ?? 'viewer';
$allowedSections = $_SESSION['user']['allowed_sections'] ?? [];

if ($role === 'super_admin') {
    $userSections = $allSections;
} elseif ($role === 'viewer') {
    $userSections = ['overview'];
} else {
    $userSections = array_values(array_intersect((array) $allowedSections, $allSections));
}

$logsRaw = fetch_json_from_url('https://reporting.anl139.site/api/logs');
$logsRaw = array_slice($logsRaw, 0, 50);

$logs = [];
foreach ($logsRaw as $log) {
    if (is_array($log)) {
        $logs[] = normalize_log_entry($log);
    }
}

$activityCountsChart = array_values(array_map(
    static fn(array $log) => $log['activityCounts'],
    $logs
));

$navTimingChart = array_values(array_filter(
    array_map(static fn(array $log) => $log['perf'], $logs),
    static fn(array $perf) => !empty($perf)
));

$dashboardData = [
    'activityCounts' => $activityCountsChart,
    'navTiming' => $navTimingChart,
];

$analystComments = [
    'overview' => 'Displays the first 10 log entries in readable format.',
    'performance' => 'Page load performance is under 500ms on average. Monitor LCP spikes.',
    'behavioral' => 'Users mainly interact via mouse movements and occasional clicks.',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="/styles/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
<header class="dashboard-header">
    <h1>Dashboard</h1>
</header>

<nav class="dashboard-tabs" aria-label="Dashboard sections">
    <?php if (in_array('overview', $userSections, true)): ?>
        <button class="tab-button active" data-tab="overview" type="button">Overview</button>
    <?php endif; ?>

    <?php if (in_array('performance', $userSections, true)): ?>
        <button class="tab-button" data-tab="performance" type="button">Performance</button>
    <?php endif; ?>

    <?php if (in_array('behavioral', $userSections, true)): ?>
        <button class="tab-button" data-tab="behavioral" type="button">Behavioral</button>
    <?php endif; ?>
</nav>

<main class="dashboard-main">
    <section id="overview" class="tab-content active">
        <button type="button" data-export-pdf="overview">Export PDF</button>
        <div class="panel">
            <h2>Overview</h2>
            <p><?= e($analystComments['overview']) ?></p>
        </div>
    </section>

    <section id="performance" class="tab-content">
        <button type="button" data-export-pdf="performance">Export PDF</button>
        <canvas id="navChart"></canvas>
        <p><?= e($analystComments['performance']) ?></p>
    </section>

    <section id="behavioral" class="tab-content">
        <button type="button" data-export-pdf="behavioral">Export PDF</button>
        <canvas id="activityChart"></canvas>
        <p><?= e($analystComments['behavioral']) ?></p>
    </section>
</main>

<script type="application/json" id="dashboard-data"><?= safe_json_encode($dashboardData) ?></script>
<script src="/js/dashboard.js"></script>
</body>
</html>
