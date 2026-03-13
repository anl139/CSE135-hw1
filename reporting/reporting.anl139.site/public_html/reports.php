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

function safeJsonDecode($value, array $default = []): array
{
    if (is_array($value)) {
        return $value;
    }

    if (!is_string($value) || trim($value) === '') {
        return $default;
    }

    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : $default;
}

function fetchLogsFromApi(string $url): array
{
    $raw = @file_get_contents($url);
    $decoded = safeJsonDecode($raw, []);

    return is_array($decoded) ? $decoded : [];
}

function toUnixSeconds($value): ?int
{
    if (!is_numeric($value)) {
        return null;
    }

    $value = (int) $value;

    // Handle either milliseconds or seconds.
    return $value > 1000000000000 ? (int) floor($value / 1000) : $value;
}

function formatClockTime($value): string
{
    $ts = toUnixSeconds($value);
    return $ts ? gmdate('H:i:s', $ts) : '-';
}

function normalizeTimestamp($value): string
{
    $ts = toUnixSeconds($value);
    if ($ts) {
        return gmdate('Y-m-d H:i:s', $ts);
    }

    return is_string($value) ? $value : '';
}

function firstN($items, int $limit = 3): array
{
    return array_slice(is_array($items) ? $items : [], 0, $limit);
}

function detectLogType(array $raw, array $data): string
{
    $type = $raw['type'] ?? $raw['log_type'] ?? $data['type'] ?? '';

    if ($type !== '') {
        return (string) $type;
    }

    if (isset($data['activityData']) || isset($data['activitySummary'])) {
        return 'activity';
    }

    if (isset($data['navTiming']) || isset($data['vitals']) || isset($data['technographics'])) {
        return 'pageview';
    }

    return 'unknown';
}

function extractMetadata(array $raw, array $data): array
{
    $type = detectLogType($raw, $data);

    $page = $data['page'] ?? '';
    if ($page === '' && !empty($data['url'])) {
        $page = parse_url($data['url'], PHP_URL_PATH) ?: $data['url'];
    }

    return [
        'sessionId' => $raw['session_id'] ?? $raw['sessionId'] ?? $data['session_id'] ?? $data['sessionId'] ?? '',
        'timestamp' => normalizeTimestamp($raw['timestamp'] ?? $data['timestamp'] ?? ''),
        'type'      => $type,
        'page'      => $page,
    ];
}

function extractActivity(array $data): array
{
    // Prefer activityData; fall back to activitySummary for older logs.
    $source = $data['activityData'] ?? $data['activitySummary'] ?? [];

    $clicksRaw = is_array($source['clicks'] ?? null) ? $source['clicks'] : [];
    $mouseRaw  = is_array($source['mouseMoves'] ?? null) ? $source['mouseMoves'] : [];
    $keysRaw   = is_array($source['keys'] ?? null) ? $source['keys'] : [];

    $scrollsRaw = $source['scrolls'] ?? $data['scrolls'] ?? [];
    $errorsRaw  = $source['errors'] ?? $data['errors'] ?? [];

    $clicks = array_map(function ($c) {
        return [
            'x' => (int) ($c['x'] ?? 0),
            'y' => (int) ($c['y'] ?? 0),
            't' => formatClockTime($c['t'] ?? null),
        ];
    }, firstN($clicksRaw, 3));

    $mouseMoves = array_map(function ($m) {
        return [
            'x' => (int) ($m['x'] ?? 0),
            'y' => (int) ($m['y'] ?? 0),
            't' => formatClockTime($m['t'] ?? null),
        ];
    }, firstN($mouseRaw, 3));

    $keys = array_map(function ($k) {
        return [
            'key' => (string) ($k['key'] ?? ''),
            't'   => formatClockTime($k['t'] ?? null),
        ];
    }, firstN($keysRaw, 3));

    $scrollCount = is_array($scrollsRaw) ? count($scrollsRaw) : (int) $scrollsRaw;
    $errorCount  = is_array($errorsRaw) ? count($errorsRaw) : (int) $errorsRaw;

    return [
        'clicks'       => $clicks,
        'mouseMoves'   => $mouseMoves,
        'keys'         => $keys,
        'scrollCount'  => $scrollCount,
        'errorCount'   => $errorCount,

        // Helpful counts for charts and summaries
        'clickCount'      => is_array($clicksRaw) ? count($clicksRaw) : (int) ($source['clickCount'] ?? 0),
        'mouseMoveCount'  => is_array($mouseRaw) ? count($mouseRaw) : (int) ($source['mouseMoveCount'] ?? 0),
        'keyCount'        => is_array($keysRaw) ? count($keysRaw) : (int) ($source['keyCount'] ?? 0),
    ];
}

function extractPerformance(array $data): array
{
    $vitals = $data['vitals'] ?? [];
    $nav    = $data['navTiming'] ?? [];

    return [
        'domInteractive'  => $nav['domInteractive'] ?? null,
        'totalLoadTime'   => $nav['totalLoadTime'] ?? null,
        'lcp'             => $vitals['lcp'] ?? null,
        'cls'             => $vitals['cls'] ?? null,
        'inp'             => $vitals['inp'] ?? null,
    ];
}

function extractTechnographics(array $data): array
{
    $tech = $data['technographics'] ?? [];

    return [
        'cores'       => $tech['cores'] ?? null,
        'memory'      => $tech['memory'] ?? null,
        'screenWidth' => $tech['screenWidth'] ?? null,
        'screenHeight'=> $tech['screenHeight'] ?? null,
    ];
}

function buildDisplayFields(array $activity, array $technographics): array
{
    $clicks = array_map(function ($c) {
        return '(' . $c['x'] . ',' . $c['y'] . ')@' . ($c['t'] ?? '-');
    }, $activity['clicks'] ?? []);

    $mouse = array_map(function ($m) {
        return '(' . $m['x'] . ',' . $m['y'] . ')@' . ($m['t'] ?? '-');
    }, $activity['mouseMoves'] ?? []);

    $keys = array_map(function ($k) {
        return ($k['key'] ?? '') . '@' . ($k['t'] ?? '-');
    }, $activity['keys'] ?? []);

    $techSummary = '-';
    if (array_filter($technographics, fn($v) => $v !== null && $v !== '')) {
        $cores  = $technographics['cores'] ?? '-';
        $memory = $technographics['memory'] ?? '-';
        $screen = ($technographics['screenWidth'] ?? '-') . 'x' . ($technographics['screenHeight'] ?? '-');
        $techSummary = $cores . ' cores, ' . $memory . ' GB, ' . $screen;
    }

    return [
        'clicks' => $clicks,
        'mouse'  => $mouse,
        'keys'   => $keys,
        'tech'   => $techSummary,
    ];
}

function parseLog(array $log): array
{
    $data = safeJsonDecode($log['data'] ?? [], []);

    $metadata       = extractMetadata($log, $data);
    $activity       = extractActivity($data);
    $performance    = extractPerformance($data);
    $technographics = extractTechnographics($data);
    $display        = buildDisplayFields($activity, $technographics);

    return [
        'raw'            => $log,
        'metadata'       => $metadata,
        'activity'       => $activity,
        'performance'    => $performance,
        'vitals'         => [
            'lcp' => $performance['lcp'],
            'cls' => $performance['cls'],
            'inp' => $performance['inp'],
        ],
        'technographics' => $technographics,
        'display'        => $display,
    ];
}

$logsRaw = fetchLogsFromApi('https://reporting.anl139.site/api/logs');
$logsRaw = array_slice($logsRaw, 0, 50);

$logs = array_map('parseLog', $logsRaw);
$overviewLogs = array_slice($logs, 0, 10);

$activitySeries = [];
$performanceSeries = [];

foreach ($logs as $log) {
    $activitySeries[] = [
        'label'      => $log['metadata']['page'] ?: ($log['metadata']['sessionId'] ?: 'Log'),
        'sessionId'  => $log['metadata']['sessionId'],
        'type'       => $log['metadata']['type'],
        'clicks'     => (int) ($log['activity']['clickCount'] ?? 0),
        'scrolls'    => (int) ($log['activity']['scrollCount'] ?? 0),
        'mouseMoves' => (int) ($log['activity']['mouseMoveCount'] ?? 0),
        'errors'     => (int) ($log['activity']['errorCount'] ?? 0),
    ];

    $perf = $log['performance'];
    if (
        $perf['domInteractive'] !== null ||
        $perf['totalLoadTime'] !== null ||
        $perf['lcp'] !== null ||
        $perf['cls'] !== null ||
        $perf['inp'] !== null
    ) {
        $performanceSeries[] = [
            'label'           => $log['metadata']['page'] ?: ($log['metadata']['sessionId'] ?: 'Log'),
            'page'            => $log['metadata']['page'] ?: '-',
            'lcp'             => $perf['lcp'],
            'cls'             => $perf['cls'],
            'inp'             => $perf['inp'],
            'domInteractive'  => $perf['domInteractive'],
            'totalLoadTime'   => $perf['totalLoadTime'],
        ];
    }
}

$analystComments = [
    'overview' => 'Displays the first 10 normalized log entries in readable format.',
    'performance' => 'Performance data is now sourced from navTiming + vitals. Watch for LCP spikes.',
    'behavioral' => 'Behavioral reports use counts only, with large scroll arrays reduced to a single total.',
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
                        <td><?= htmlspecialchars($l['metadata']['sessionId'] ?? '') ?></td>
                        <td><?= htmlspecialchars($l['metadata']['type'] ?? '') ?></td>
                        <td><?= htmlspecialchars($l['metadata']['timestamp'] ?? '') ?></td>
                        <td><?= htmlspecialchars(implode(', ', $l['display']['mouse'] ?? [])) ?></td>
                        <td><?= htmlspecialchars(implode(', ', $l['display']['clicks'] ?? [])) ?></td>
                        <td><?= htmlspecialchars(implode(', ', $l['display']['keys'] ?? [])) ?></td>
                        <td><?= htmlspecialchars((string)($l['activity']['errorCount'] ?? 0)) ?></td>
                        <td><?= htmlspecialchars($l['display']['tech'] ?? '-') ?></td>
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
                    <?php foreach ($performanceSeries as $p): ?>
                        <tr>
                        <td><?= htmlspecialchars($p['page'] ?? '-') ?></td>
                        <td><?= htmlspecialchars((string)($p['lcp'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string)($p['cls'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string)($p['inp'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string)($p['domInteractive'] ?? '-')) ?></td>
                        <td><?= htmlspecialchars((string)($p['totalLoadTime'] ?? '-')) ?></td>
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
    'activitySeries' => $activitySeries,
    'performanceSeries' => $performanceSeries,
], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>
</script>

<script src="/js/dashboard.js"></script>
</body>
</html>
</body>
</html>
