<?php
require 'auth.php';
require_auth();

// ----------------- CONFIG -----------------
$allSections = ['overview','performance','behavioral'];

$role = $_SESSION['user']['role'] ?? '';
$userSections = $_SESSION['user']['allowed_sections'] ?? [];
if ($role === 'super_admin') $userSections = $allSections;
elseif ($role === 'viewer') $userSections = ['overview'];

// ----------------- FETCH LOGS -----------------
$logsRaw = json_decode(file_get_contents("https://reporting.anl139.site/api/logs"), true) ?? [];
$logsRaw = array_slice($logsRaw, 0, 50);

// ----------------- PROCESS LOGS -----------------
$logs = array_map(function($log) {
    $data = json_decode($log['data'] ?? '{}', true);
    $activity = $data['activitySummary'] ?? [];
    $vitals = $data['vitals'] ?? [];
    $tech = $data['technographics'] ?? [];

    // Limit first 3 entries for display
    $sliceData = fn($arr) => array_slice($arr ?? [], 0, 3);

    // Prepare activity summaries
    $clicks = array_map(fn($c) => "({$c['x']},{$c['y']})@" . (!empty($c['t']) ? date('H:i:s', $c['t']/1000) : '-'), $sliceData($activity['clicks']));
    $mouse = array_map(fn($m) => "({$m['x']},{$m['y']})@" . (!empty($m['t']) ? date('H:i:s', $m['t']/1000) : '-'), $sliceData($activity['mouseMoves']));
    $keys  = array_map(fn($k) => "{$k['key']}@" . (!empty($k['t']) ? date('H:i:s', $k['t']/1000) : '-'), $sliceData($activity['keys']));

    // Technographics summary
    $techSummary = '';
    if($tech){
        $cores = $tech['cores'] ?? '-';
        $memory = $tech['memory'] ?? '-';
        $network = $tech['network']['effectiveType'] ?? '-';
        $screen = ($tech['screenWidth'] ?? '-') . "x" . ($tech['screenHeight'] ?? '-');
        $techSummary = "$cores cores, $memory GB, $network, $screen";
    }

    // Performance data
    $perf = [];
    if(isset($data['navTiming'])){
        $perf = [
            'page' => $data['page'] ?? parse_url($data['url'] ?? '', PHP_URL_PATH) ?? 'Unknown',
            'lcp'  => $vitals['lcp'] ?? null,
            'cls'  => $vitals['cls'] ?? null,
            'inp'  => $vitals['inp'] ?? null,
            'domContentLoaded' => $data['navTiming']['domInteractive'] ?? null,
            'loadTime' => $data['navTiming']['totalLoadTime'] ?? null
        ];
    }

    // Activity counts
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

// Prepare chart datasets
$activityCountsChart = array_map(fn($l) => $l['activityCounts'], $logs);
$navTimingChart = array_filter(array_map(fn($l) => $l['perf'], $logs));

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
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
body { font-family:sans-serif; margin:0; }
.dashboard-layout { display:grid; grid-template-columns:220px 1fr; grid-template-rows:60px 1fr; min-height:100vh; }
.dashboard-header { grid-column:1/-1; background:#2c3e50; color:#fff; display:flex; align-items:center; padding:0 20px; gap:20px; }
.sidebar { background:#34495e; padding-top:20px; display:flex; flex-direction:column; }
.sidebar a { color:#ecf0f1; text-decoration:none; padding:10px 20px; }
.sidebar a.active { background:#1abc9c; color:#fff; }
.main-content { padding:20px; overflow:auto; }
table { border-collapse:collapse; width:100%; margin-bottom:20px; }
th,td { border:1px solid #ccc; padding:6px; }
canvas { margin-bottom:30px; max-width:100%; }
.comments { background:#f6f6f6; padding:10px; border-left:4px solid #777; margin-bottom:15px; }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
<div class="dashboard-layout">
    <header class="dashboard-header">
        <h1>Analytics Dashboard</h1>
        <span>User: <?= htmlspecialchars($_SESSION['user']['displayName']) ?> (<?= htmlspecialchars($role) ?>)</span>
        <button onclick="location.href='logout.php'">Logout</button>
    </header>
    <nav class="sidebar">
        <?php if(in_array('overview', $userSections)): ?>
            <a href="#overview" class="active" data-tab="overview">Overview</a>
        <?php endif; ?>
        <?php if(in_array('performance', $userSections)): ?>
            <a href="#performance" data-tab="performance">Performance</a>
        <?php endif; ?>
        <?php if(in_array('behavioral', $userSections)): ?>
            <a href="#behavioral" data-tab="behavioral">Behavioral</a>
        <?php endif; ?>
    </nav>
    <main class="main-content">
    <?php if(in_array('overview', $userSections)): ?>
    <div id="overview" class="tab-content">
        <h2>Overview</h2>
        <button onclick="exportPDF('overview')">Export as PDF</button>
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
            <?php foreach(array_slice($logs, 0, 10) as $i => $l): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($l['raw']['session_id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['raw']['log_type'] ?? '') ?></td>
                    <td><?= htmlspecialchars($l['raw']['timestamp'] ?? '') ?></td>
                    <td><?= htmlspecialchars(implode(", ", $l['mouse'])) ?></td>
                    <td><?= htmlspecialchars(implode(", ", $l['clicks'])) ?></td>
                    <td><?= htmlspecialchars(implode(", ", $l['keys'])) ?></td>
                    <td><?= htmlspecialchars($l['activityCounts']['errors'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($l['tech']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if(in_array('performance', $userSections)): ?>
    <div id="performance" class="tab-content" style="display:none;">
        <h2>Performance Reports</h2>
        <button onclick="exportPDF('performance')">Export as PDF</button>
        <div class="comments"><?= htmlspecialchars($analystComments['performance']) ?></div>
        <canvas id="navChart"></canvas>
        <table>
            <thead>
                <tr><th>Page</th><th>LCP</th><th>CLS</th><th>INP</th><th>DOM Interactive</th><th>Total Load Time</th></tr>
            </thead>
            <tbody>
            <?php foreach($navTimingChart as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['page'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['lcp'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['cls'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['inp'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['domContentLoaded'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($p['loadTime'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <?php if(in_array('behavioral', $userSections)): ?>
    <div id="behavioral" class="tab-content" style="display:none;">
        <h2>Behavioral Reports</h2>
        <button onclick="exportPDF('behavioral')">Export as PDF</button>
        <div class="comments"><?= htmlspecialchars($analystComments['behavioral']) ?></div>
        <canvas id="activityChart"></canvas>
        <table>
            <thead>
                <tr><th>#</th><th>Mouse</th><th>Clicks</th><th>Keys</th></tr>
            </thead>
            <tbody>
            <?php foreach(array_slice($logs,0,10) as $i => $l): ?>
                <tr>
                    <td><?= $i+1 ?></td>
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

<script>
const activityCounts = <?= json_encode(array_column($logs,'activityCounts')) ?>;
const navTiming = <?= json_encode(array_values(array_filter(array_column($logs,'perf')))) ?>;

// Tab navigation
document.querySelectorAll('.sidebar a').forEach(a=>{
    a.addEventListener('click', e=>{
        e.preventDefault();
        document.querySelectorAll('.sidebar a').forEach(l=>l.classList.remove('active'));
        a.classList.add('active');
        const tab = a.dataset.tab;
        document.querySelectorAll('.tab-content').forEach(c=>{
            c.style.display = (c.id === tab) ? 'block' : 'none';
        });
    });
});

// Performance chart
<?php if(in_array('performance', $userSections)): ?>
new Chart(document.getElementById('navChart'), {
    type:'bar',
    data:{
        labels: navTiming.map((_,i)=>'Log '+(i+1)),
        datasets:[
            {label:'DOM Interactive', data: navTiming.map(n=>n.domContentLoaded)},
            {label:'Total Load', data: navTiming.map(n=>n.loadTime)}
        ]
    }
});
<?php endif; ?>

// Behavioral chart
<?php if(in_array('behavioral', $userSections)): ?>
new Chart(document.getElementById('activityChart'), {
    type:'bar',
    data:{
        labels: activityCounts.map((_,i)=>'Log '+(i+1)),
        datasets:[
            {label:'Clicks', data: activityCounts.map(a=>a.clicks)},
            {label:'Scrolls', data: activityCounts.map(a=>a.scrolls)},
            {label:'MouseMoves', data: activityCounts.map(a=>a.mouseMoves)},
            {label:'Errors', data: activityCounts.map(a=>a.errors)}
        ]
    }
});
<?php endif; ?>

// Export PDF
// Export PDF
function exportPDF(tabId) {
    const el = document.getElementById(tabId);
    const hiddenTabs = document.querySelectorAll('.tab-content');

    // Hide other tabs temporarily
    hiddenTabs.forEach(t => { if(t.id !== tabId) t.style.display = 'none'; });

    html2pdf()
        .set({
            margin: 0.5,
            filename: tabId + '_report.pdf',
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' } // <-- added for wide tables
        })
        .from(el)
        .save()
        .then(() => {
            // Restore visibility of other tabs
            hiddenTabs.forEach(t => { t.style.display = (document.querySelector(`.sidebar a.active`).dataset.tab === t.id) ? 'block' : 'none'; });
        });
}
</script>
