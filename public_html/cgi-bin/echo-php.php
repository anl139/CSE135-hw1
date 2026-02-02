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
   Read body + parse data
------------------------------ */
$raw = file_get_contents('php://input');
$type = $_SERVER['CONTENT_TYPE'] ?? '';

$data = [];

if (str_contains($type, 'application/json')) {
    $data = json_decode($raw, true) ?? [];
} else {
    match ($method) {
        'GET'    => $data = $_GET,
        'POST'   => $data = $_POST,
        default  => parse_str($raw, $data),
    };
}

unset($data['_method']);

/* -----------------------------
   Build response
------------------------------ */
$response = [
    'method'     => $method,
    'transport'  => $_SERVER['REQUEST_METHOD'] ?? '',
    'host'       => $_SERVER['HTTP_HOST'] ?? '',
    'time'       => date('c'),
    'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
    'headers'    => function_exists('getallheaders') ? getallheaders() : [],
    'data'       => $data,
    'raw_body'   => $raw
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

