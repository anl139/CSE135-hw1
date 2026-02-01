<?php
/**
 * echo-php.php
 * CGI echo endpoint for Web Programming demos
 */

/* --------------------------------------------------
 * Determine the EFFECTIVE HTTP method
 * (supports POST + _method override)
 * -------------------------------------------------- */
function effective_method() {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'POST') {

        if (!empty($_POST['_method'])) {
            $override = strtoupper(trim($_POST['_method']));
            if (in_array($override, ['PUT', 'DELETE', 'PATCH'])) {
                return $override;
            }
        }

        if (!empty($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            $override = strtoupper(trim($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']));
            if (in_array($override, ['PUT', 'DELETE', 'PATCH'])) {
                return $override;
            }
        }
    }

    return $method;
}

/* --------------------------------------------------
 * Gather request metadata
 * -------------------------------------------------- */
$effective_method = effective_method();
$transport_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$host        = $_SERVER['HTTP_HOST'] ?? '';
$time        = date('c');
$ip          = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent  = $_SERVER['HTTP_USER_AGENT'] ?? '';
$headers     = function_exists('getallheaders') ? getallheaders() : [];
$content_type = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');
$raw_body    = file_get_contents('php://input');

/* --------------------------------------------------
 * Parse incoming data
 * -------------------------------------------------- */
$data = [];

/* JSON payload */
if (stripos($content_type, 'application/json') !== false) {

    $decoded = json_decode($raw_body, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $data = $decoded;
    }

/* x-www-form-urlencoded or query string */
} else {

    if ($transport_method === 'GET') {
        // GET → query string
        $data = $_GET;

    } elseif ($transport_method === 'POST') {
        // POST (including POST + _method override)
        $data = $_POST;

    } else {
        // True PUT / DELETE (rare under Apache CGI)
        parse_str($raw_body, $data);
    }

    unset($data['_method']);
}

/* --------------------------------------------------
 * Build response
 * -------------------------------------------------- */
$response = [
    'method'          => $effective_method,
    'transport'       => $transport_method,
    'host'            => $host,
    'time'            => $time,
    'ip'              => $ip,
    'user_agent'      => $user_agent,
    'headers'         => $headers,
    'content_type'    => $content_type,
    'query_string'    => $_SERVER['QUERY_STRING'] ?? '',
    'data'            => $data,
    'raw_body'        => $raw_body
];

/* --------------------------------------------------
 * Output JSON response
 * -------------------------------------------------- */
header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
exit;

