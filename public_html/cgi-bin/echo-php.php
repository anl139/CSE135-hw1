<?php
header('Content-Type: application/json; charset=utf-8');

/* -----------------------------
   Determine effective method
------------------------------ */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $override = $_POST['_method']
        ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']
        ?? null;

    if ($override && in_array(strtoupper($override), ['PUT','DELETE','PATCH'])) {
        $method = strtoupper($override);
    }
}

/* -----------------------------
   Read raw body + content type
------------------------------ */
$raw_body = file_get_contents('php://input');
$content_type = $_SERVER['CONTENT_TYPE'] ?? '';

/* -----------------------------
   Parse data for different content types
------------------------------ */
$data = [];
$www_data = [];  // specifically for www-form

if (str_contains($content_type, 'application/json')) {
    $data = json_decode($raw_body, true) ?? [];
} elseif (str_contains($content_type, 'application/x-www-form-urlencoded')) {
    parse_str($raw_body, $data);
    $www_data = $_POST;  // capture $_POST specifically for www-form
} else {
    match ($method) {
        'GET'    => $data = $_GET,
        'POST'   => $data = $_POST,
        default  => parse_str($raw_body, $data),
    };
}

unset($data['_method']);

/* -----------------------------
   Build response showing both
------------------------------ */
$response = [
    'method'        => $method,
    'transport'     => $_SERVER['REQUEST_METHOD'] ?? '',
    'host'          => $_SERVER['HTTP_HOST'] ?? '',
    'time'          => date('c'),
    'ip'            => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'content_type'  => $content_type,
    'headers'       => function_exists('getallheaders') ? getallheaders() : [],
    'data'          => $data,       // parsed based on content-type
    'www_post'      => $www_data,   // only populated for www-form
    'raw_body'      => $raw_body
];

/* -----------------------------
   Output JSON
------------------------------ */
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
