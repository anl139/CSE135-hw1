<?php
// submit.php - forwards requests to the real CGI scripts

$language = $_REQUEST['language'] ?? 'php';
$method = $_REQUEST['method'] ?? $_SERVER['REQUEST_METHOD'];

// Map form value to actual CGI path
$targets = [
    'php'  => '/cgi-bin/echo-php.php',
    'perl' => '/cgi-bin/echo-perl.pl',
    'node' => '/cgi-bin/echo-node.js',
];

if (!isset($targets[$language])) {
    http_response_code(400);
    echo "Invalid language selected.";
    exit;
}

$target = $_SERVER['DOCUMENT_ROOT'] . $targets[$language];

// Prepare data
$data = [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? 'application/x-www-form-urlencoded';

if ($method === 'GET') {
    $data = $_GET;
} else {
    $raw = file_get_contents('php://input');
    if (stripos($content_type, 'application/json') !== false) {
        $json = json_decode($raw, true);
        $data = is_array($json) ? $json : [];
    } else {
        parse_str($raw, $data);
    }
}

// Forward request using stream context
$options = [
    'http' => [
        'method'  => $method,
        'header'  => "Content-Type: $content_type\r\n",
        'content' => http_build_query($data),
    ]
];

$context = stream_context_create($options);
$response = @file_get_contents($target, false, $context);

if ($response === false) {
    http_response_code(500);
    echo "Error contacting CGI script.";
    exit;
}

// Output CGI response
echo $response;
