<?php
header("Cache-Control: no-cache");
header("Content-Type: text/html");

$method = $_SERVER['REQUEST_METHOD'];
$ip = $_SERVER['REMOTE_ADDR'];
$ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$time = date("r");

// Read body (for PUT / DELETE / JSON)
$rawBody = file_get_contents("php://input");

// Parse data
$data = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if ($method === 'GET') {
    $data = $_GET;
} elseif (str_contains($contentType, 'application/json')) {
    $data = json_decode($rawBody, true) ?? [];
} else {
    $data = $_POST;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Request Echo</title>
</head>
<body>

<h1 align="center">Request Echo</h1>
<hr>

<b>Method:</b> <?= htmlspecialchars($method) ?><br/>
<b>IP Address:</b> <?= htmlspecialchars($ip) ?><br/>
<b>User Agent:</b> <?= htmlspecialchars($ua) ?><br/>
<b>Time:</b> <?= htmlspecialchars($time) ?><br/>

<h3>Received Data:</h3>
<pre><?= htmlspecialchars(print_r($data, true)) ?></pre>

</body>
</html>
