<?php
// CGI echo endpoint
function effective_method() {
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'POST') {
if (isset($_POST['_method'])) {
$override = strtoupper(trim($_POST['_method']));
if (in_array($override, ['PUT', 'DELETE', 'PATCH'])) $method = $override;
}
if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
$override = strtoupper(trim($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']));
if (in_array($override, ['PUT', 'DELETE', 'PATCH'])) $method = $override;
}
}
return $method;
}


$method = effective_method();
$host = $_SERVER['HTTP_HOST'] ?? '';
$time = date('c');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
$raw_body = file_get_contents('php://input');
$content_type = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');


$data = [];
if (stripos($content_type,'application/json')!==false) {
$decoded=json_decode($raw_body,true);
if (json_last_error()===JSON_ERROR_NONE) $data=$decoded;
} else {
if ($method==='GET') $data=$_GET;
else $data=$_POST;
unset($data['_method']);
}
$response=[
'method'=>$method,
'host'=>$host,
'time'=>$time,
'ip'=>$ip,
'user_agent'=>$user_agent,
'headers'=>$headers,
'content_type'=>$content_type,
'data'=>$data,
'raw_body'=>$raw_body
];


header('Content-Type: application/json; charset=utf-8');
echo json_encode($response, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
exit;
?>

