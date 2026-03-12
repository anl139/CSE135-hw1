<?php
require 'auth.php';
require_auth();

// Define available sections
$allSections = ['overview','performance','behavioral'];

// Determine user role & allowed sections
$role = $_SESSION['user']['role'] ?? '';
$userSections = $_SESSION['user']['allowed_sections'] ?? [];

if ($role === 'super_admin') {
    $userSections = $allSections;
} elseif ($role === 'viewer') {
    $userSections = ['overview'];
}

// Fetch logs
$logs = json_decode(file_get_contents("https://reporting.anl139.site/api/logs"), true) ?? [];
$logs = array_slice($logs,0,50);

// Prepare data
$activityCounts = [];
$pageVisits = [];
$navTiming = [];
$behavioralData = [];
$performanceData = [];

foreach ($logs as $log) {
    $data = json_decode($log['data'] ?? '{}', true);
    $activity = $data['activitySummary'] ?? [];

    if (isset($data['navTiming'])) {
        $navTiming[] = [
            'domContentLoaded' => $data['navTiming']['domInteractive'] ?? null,
            'loadTime' => $data['navTiming']['totalLoadTime'] ?? null
        ];

        $performanceData[] = [
            'page' => $data['page'] ?? parse_url($data['url'] ?? '', PHP_URL_PATH) ?? 'Unknown',
            'lcp' => $data['vitals']['lcp'] ?? null,
            'cls' => $data['vitals']['cls'] ?? null,
            'inp' => $data['vitals']['inp'] ?? null
        ];
    }

    $behavioralData[] = [
        'mouseMoves' => $activity['mouseMoves'] ?? [],
        'clicks' => $activity['clicks'] ?? [],
        'keys' => $activity['keys'] ?? [],
        'idleTimes' => $activity['idleTimes'] ?? []
    ];

    $activityCounts[] = [
        'clicks' => count($activity['clicks'] ?? []),
        'scrolls' => count($activity['scrolls'] ?? []),
        'mouseMoves' => count($activity['mouseMoves'] ?? []),
        'errors' => $data['errorCount'] ?? 0
    ];

    $page = $data['page'] ?? parse_url($data['url'] ?? '', PHP_URL_PATH) ?? 'Unknown';
    $pageVisits[$page] = ($pageVisits[$page] ?? 0) + 1;
}

$analystComments = [
    'overview' => "Displays the first 10 log entries from the database for quick analysis.",
    'performance' => "Page load performance is generally under 500ms which is within RAIL guidelines. Monitoring of LCP spikes is recommended.",
    'behavioral' => "Users primarily interact through mouse movement and occasional clicks around navigation areas."
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
                    <tr><th>ID</th><th>Session</th><th>Type</th><th>Timestamp</th><th>Summary</th></tr>
                </thead>
                <tbody>
                <?php foreach(array_slice($logs,0,10) as $i=>$log): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><?= htmlspecialchars($log['session_id'] ?? '') ?></td>
                        <td><?= htmlspecialchars($log['log_type'] ?? '') ?></td>
                        <td><?= htmlspecialchars($log['timestamp'] ?? '') ?></td>
                        <td><pre><?= json_encode(json_decode($log['data'] ?? '{}',true),JSON_PRETTY_PRINT) ?></pre></td>
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
                <thead><tr><th>Page</th><th>LCP</th><th>CLS</th><th>INP</th></tr></thead>
                <tbody>
                <?php foreach($performanceData as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['page']) ?></td>
                        <td><?= htmlspecialchars($row['lcp'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['cls'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($row['inp'] ?? '-') ?></td>
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
                <thead><tr><th>Mouse X</th><th>Mouse Y</th><th>Timestamp</th></tr></thead>
                <tbody>
                <?php
                foreach($behavioralData as $b){
                    foreach(array_slice($b['mouseMoves'],0,10) as $m){
                        $x = $m['x'] ?? '-';
                        $y = $m['y'] ?? '-';
                        $t = !empty($m['t']) ? date('Y-m-d H:i:s',$m['t']/1000) : '-';
                        echo "<tr><td>$x</td><td>$y</td><td>$t</td></tr>";
                    }
                }
                ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </main>
</div>

<script>
const activityCounts = <?= json_encode($activityCounts) ?>;
const navTiming = <?= json_encode($navTiming) ?>;

// Tab navigation
document.querySelectorAll('.sidebar a').forEach(a=>{
    a.addEventListener('click', e=>{
        e.preventDefault();
        document.querySelectorAll('.sidebar a').forEach(link=>link.classList.remove('active'));
        a.classList.add('active');
        const tab = a.dataset.tab;
        document.querySelectorAll('.tab-content').forEach(c=>{
            c.style.display = (c.id === tab) ? 'block':'none';
        });
    });
});

// Render charts only if user has access
<?php if(in_array('behavioral', $userSections)): ?>
new Chart(document.getElementById('activityChart'),{
    type:'bar',
    data:{
        labels:activityCounts.map((_,i)=>'Log '+(i+1)),
        datasets:[
            {label:'Clicks',data:activityCounts.map(a=>a.clicks)},
            {label:'Scrolls',data:activityCounts.map(a=>a.scrolls)},
            {label:'MouseMoves',data:activityCounts.map(a=>a.mouseMoves)},
            {label:'Errors',data:activityCounts.map(a=>a.errors)}
        ]
    }
});
<?php endif; ?>

<?php if(in_array('performance', $userSections)): ?>
new Chart(document.getElementById('navChart'),{
    type:'bar',
    data:{
        labels:navTiming.map((_,i)=>'Log '+(i+1)),
        datasets:[
            {label:'DOM Interactive',data:navTiming.map(n=>n.domContentLoaded)},
            {label:'Load Time',data:navTiming.map(n=>n.loadTime)}
        ]
    }
});
<?php endif; ?>
function exportPDF(tabId) {
    const element = document.getElementById(tabId);

    // Optional: temporarily remove hidden tabs to only export visible content
    const hiddenTabs = document.querySelectorAll('.tab-content');
    hiddenTabs.forEach(t => { if(t.id !== tabId) t.style.display='none'; });

    html2pdf()
        .set({ margin:0.5, filename: tabId + '_report.pdf', html2canvas:{scale:2} })
        .from(element)
        .save()
        .then(()=> {
            // Restore other tabs visibility
            hiddenTabs.forEach(t => { if(t.id !== tabId && document.querySelector('.sidebar a.active').dataset.tab === t.id) t.style.display='block'; });
        });
}
</script>
</body>
</html>
