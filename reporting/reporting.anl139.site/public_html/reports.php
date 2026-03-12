<?php
require 'auth.php';
require_auth();

$sections = ['performance','behavioral','reports'];

$role = $_SESSION['user']['role'] ?? '';
$userSections = $_SESSION['user']['allowed_sections'] ?? [];

if ($role === 'super_admin') {
    $userSections = $sections;
}

$logs = json_decode(file_get_contents("https://reporting.anl139.site/api/logs"), true) ?? [];
$logs = array_slice($logs,0,50);

$activityCounts = [];
$pageVisits = [];
$navTiming = [];
$behavioralData = [];
$performanceData = [];

foreach ($logs as $log) {

    $data = json_decode($log['data'] ?? '{}', true);

    $activity = $data['activitySummary'] ?? [];

    /* ---------- PERFORMANCE DATA ---------- */

    if (isset($data['navTiming'])) {

        $domContentLoaded = $data['navTiming']['domInteractive'] ?? null;
        $loadTime = $data['navTiming']['totalLoadTime'] ?? null;

        $navTiming[] = [
            'domContentLoaded' => $domContentLoaded,
            'loadTime' => $loadTime
        ];

        $performanceData[] = [
            'page' => $data['page'] ?? parse_url($data['url'] ?? '', PHP_URL_PATH) ?? 'Unknown',
            'lcp' => $data['vitals']['lcp'] ?? null,
            'cls' => $data['vitals']['cls'] ?? null,
            'inp' => $data['vitals']['inp'] ?? null
        ];
    }

    /* ---------- BEHAVIORAL DATA ---------- */

    $behavioralData[] = [
        'mouseMoves' => $activity['mouseMoves'] ?? [],
        'clicks' => $activity['clicks'] ?? [],
        'keys' => $activity['keys'] ?? [],
        'idleTimes' => $activity['idleTimes'] ?? []
    ];

    /* ---------- ACTIVITY COUNTS ---------- */

    $activityCounts[] = [
        'clicks' => count($activity['clicks'] ?? []),
        'scrolls' => count($activity['scrolls'] ?? []),
        'mouseMoves' => count($activity['mouseMoves'] ?? []),
        'errors' => $data['errorCount'] ?? 0
    ];

    $page = $data['page'] ?? parse_url($data['url'] ?? '', PHP_URL_PATH) ?? 'Unknown';

    $pageVisits[$page] = ($pageVisits[$page] ?? 0) + 1;
}

/* ---------- ANALYST COMMENTS ---------- */

$analystComments = [
    'performance' => "Page load performance is generally under 500ms which is within RAIL guidelines. Monitoring of LCP spikes is recommended.",
    'behavioral' => "Users primarily interact through mouse movement and occasional clicks around navigation areas.",
    'reports' => "Saved reports summarize historical analytics data for stakeholders."
];

?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Reports Dashboard</title>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body { font-family:sans-serif; padding:20px; }
table { border-collapse:collapse; width:100%; margin-bottom:30px; }
th,td { border:1px solid #ccc; padding:6px; }
canvas { margin-bottom:30px; }
.comments { background:#f6f6f6; padding:10px; border-left:4px solid #777; margin-bottom:15px; }
</style>

</head>
<body>

<h1>Reports Dashboard</h1>

<p>
User: <?= htmlspecialchars($_SESSION['user']['displayName']) ?>
(<?= htmlspecialchars($role) ?>)
|
<a href="logout.php">Logout</a>
</p>

<?php if(in_array('performance',$userSections)): ?>

<h2>Performance Reports</h2>

<div class="comments">
<?= htmlspecialchars($analystComments['performance']) ?>
</div>

<canvas id="navChart"></canvas>

<table>
<thead>
<tr>
<th>Page</th>
<th>LCP</th>
<th>CLS</th>
<th>INP</th>
</tr>
</thead>

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

<?php endif; ?>


<?php if(in_array('behavioral',$userSections)): ?>

<h2>Behavioral Reports</h2>

<div class="comments">
<?= htmlspecialchars($analystComments['behavioral']) ?>
</div>

<canvas id="activityChart"></canvas>

<table>
<thead>
<tr>
<th>Mouse X</th>
<th>Mouse Y</th>
<th>Timestamp</th>
</tr>
</thead>

<tbody>

<?php
foreach($behavioralData as $b){
foreach(array_slice($b['mouseMoves'],0,10) as $m){
?>

<tr>
<td><?= $m['x'] ?? '-' ?></td>
<td><?= $m['y'] ?? '-' ?></td>
<td><?= $m['t'] ?? '-' ?></td>
</tr>

<?php }} ?>

</tbody>
</table>

<?php endif; ?>


<?php if($role === 'viewer'): ?>

<h2>Saved Reports</h2>

<div class="comments">
<?= htmlspecialchars($analystComments['reports']) ?>
</div>

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

<?php foreach($logs as $i=>$log): ?>

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

<?php endif; ?>


<script>

const activityCounts = <?= json_encode($activityCounts) ?>;
const navTiming = <?= json_encode($navTiming) ?>;

<?php if(in_array('behavioral',$userSections)): ?>

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


<?php if(in_array('performance',$userSections)): ?>

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

</script>

</body>
</html>
