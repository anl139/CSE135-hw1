<?php
// Headers
header("Cache-Control: no-cache");
header("Content-Type: text/html");

// Data
$date = date("r"); // RFC 2822 format, similar to Perl localtime
$ip = $_SERVER['REMOTE_ADDR'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Hello CGI World</title>
</head>
<body>

<h1 align="center">Hello Andrew Lam</h1>
<hr/>
<p>Hello World</p>
<p>This page was generated with the PHP programming language</p>

<p>This program was generated at: <?= htmlspecialchars($date) ?></p>
<p>Your current IP Address is: <?= htmlspecialchars($ip) ?></p>

</body>
</html>
