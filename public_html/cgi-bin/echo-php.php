#!/usr/bin/php
<?php
// Disable caching
header("Cache-Control: no-cache");
header("Content-Type: text/html; charset=UTF-8");

// HTML header
echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>General Request Echo</title>
</head>
<body>
<h1 align="center">General Request Echo</h1>
<hr>
HTML;

// HTTP protocol
$protocol = $_SERVER['SERVER_PROTOCOL'] ?? '';
echo "<p><b>HTTP Protocol:</b> $protocol</p>";

// HTTP method
$method = $_SERVER['REQUEST_METHOD'] ?? '';
echo "<p><b>HTTP Method:</b> $method</p>";

// Query string
$query_string = $_SERVER['QUERY_STRING'] ?? '';
echo "<p><b>Query String:</b> $query_string</p>";

// Read message body (POST, PUT, DELETE, etc.)
$body = file_get_contents('php://input');
echo "<p><b>Message Body:</b> " . htmlentities($body) . "</p>";

// Print headers for reference
echo "<h3>Headers:</h3><pre>";
foreach (getallheaders() as $name => $value) {
    echo "$name: $value\n";
}
echo "</pre>";

// Print IP address
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
echo "<p><b>Your IP:</b> $ip</p>";

// HTML footer
echo "</body></html>";
?>
