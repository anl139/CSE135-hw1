<?php
// echo-php.php

// Disable caching
header("Cache-Control: no-cache, must-revalidate");
header("Content-Type: text/html; charset=UTF-8");

// Detect method
$method = $_SERVER['REQUEST_METHOD'];

// Detect input data based on method
$input_data = [];
if ($method === 'GET') {
    $input_data = $_GET;
} elseif ($method === 'POST') {
    $input_data = $_POST;
} else {
    // For PUT or DELETE, read raw input and try to parse JSON
    $raw_input = file_get_contents("php://input");
    $decoded = json_decode($raw_input, true);
    $input_data = $decoded ?? ['raw' => $raw_input];
}

// Get some request info
$hostname = $_SERVER['SERVER_NAME'];
$ip = $_SERVER['REMOTE_ADDR'];
$time = date("Y-m-d H:i:s");
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Echo PHP</title>
</head>
<body>
    <h1>Echo PHP Demo</h1>
    <p><b>Method:</b> <?= htmlentities($method) ?></p>
    <p><b>Hostname:</b> <?= htmlentities($hostname) ?></p>
    <p><b>IP:</b> <?= htmlentities($ip) ?></p>
    <p><b>Time:</b> <?= htmlentities($time) ?></p>
    <p><b>User Agent:</b> <?= htmlentities($user_agent) ?></p>
    <hr>
    <h2>Received Data:</h2>
    <pre><?php print_r($input_data); ?></pre>
</body>
</html>
