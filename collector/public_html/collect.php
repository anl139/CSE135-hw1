<?php
header('Content-Type: application/json');
$allowed_origins = [
    'https://test.anl139.site',
    'https://www.test.anl139.site'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins, true)) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Credentials: true');
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Origin not allowed']);
    exit;
}
header('Access-Control-Allow-Methods: POST, OPTIONS'); 
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}
$data = file_get_contents("php://input");
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}
$json = json_decode($data, true);
if ($json === null) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}
$dsn = "pgsql:host=localhost;port=5432;dbname=collector_db";
$user = "collector_andrew";
$pass = "Anny2001";
try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    $stmt = $pdo->prepare(
        "INSERT INTO logs (session_id, log_type, timestamp, data) VALUES (:session_id, :log_type, :timestamp, :data)"
    );

    $stmt->execute([
        ':session_id' => $json['sessionId'] ?? 'unknown',
        ':log_type' => $json['type'] ?? 'unknown',
        ':timestamp' => date('Y-m-d H:i:s'),
        ':data' => json_encode($json)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error', 'message' => $e->getMessage()]);
    exit;
}

// Success
http_response_code(204);
exit;
