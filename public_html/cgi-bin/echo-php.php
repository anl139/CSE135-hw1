#!/usr/bin/php
<?php
// echo-php.php
// This script echoes back everything about the request: method, IP, headers, data

// No caching
header("Cache-Control: no-cache");

// Determine client IP
function client_ip() {
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$host = $_SERVER['HTTP_HOST'] ?? gethostname();
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$time = date('r');
$ip = client_ip();
$content_type = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
$raw_body = file_get_contents('php://input');

// Parse data depending on method and content type
$parsed = [];
if ($method === 'GET') {
    $parsed = $_GET;
} elseif ($method === 'POST') {
    $parsed = $_POST;
    // If JSON, decode
    if (empty($parsed) && stripos($content_type, 'application/json') !== false) {
        $json = json_decode($raw_body, true);
        if (is_array($json)) $parsed = $json;
    }
} else { // PUT, DELETE
    if (stripos($content_type, 'application/json') !== false) {
        $json = json_decode($raw_body, true);
        if (is_array($json)) $parsed = $json;
    } else {
        parse_str($raw_body, $parsed);
    }
}

// Output as HTML
header("Content-Type: text/html; charset=utf-8");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Echo PHP</title>
    <style>
        body { font-family: sans-serif; margin: 2em; }
        pre { background: #f0f0f0; padding: 1em; }
        button { margin-top: 1em; padding: 0.5em 1em; }
    </style>
</head>
<body>
<h1>Echo Endpoint (PHP)</h1>
<ul>
    <li><strong>Hostname:</strong> <?= htmlspecialchars($host) ?></li>
    <li><strong>Method:</strong> <?= htmlspecialchars($method) ?></li>
    <li><strong>Time:</strong> <?= htmlspecialchars($time) ?></li>
    <li><strong>IP Address:</strong> <?= htmlspecialchars($ip) ?></li>
    <li><strong>User-Agent:</strong> <?= htmlspecialchars($ua) ?></li>
    <li><strong>Content-Type:</strong> <?= htmlspecialchars($content_type) ?></li>
</ul>

<h2>Parsed Data</h2>
<pre><?= htmlspecialchars(print_r($parsed, true)) ?></pre>

<h2>Raw Body</h2>
<pre><?= htmlspecialchars($raw_body) ?></pre>

<!-- Back to Form Button -->
<form action="../echo-form.html" method="get">
    <button type="submit">Back to Form</button>
</form>

</body>
</html>

