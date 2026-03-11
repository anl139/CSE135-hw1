<?php
header('Content-Type: application/json');

// DB connection
$dsn = "pgsql:host=localhost;port=5432;dbname=collector_db";
$user = "collector_andrew";
$pass = "Anny2001";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "DB connection failed"]);
    exit;
}

// Parse ID from URL: /api/logs/{id}
$path = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));
$id = $path[0] ?? null;

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {

    // GET /api/logs OR GET /api/logs/{id}
    case 'GET':
        if ($id) {
            $stmt = $pdo->prepare("SELECT * FROM logs WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->query("SELECT * FROM logs ORDER BY id DESC");
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode($result);
        break;

    // POST /api/logs
    case 'POST':
        $body = json_decode(file_get_contents("php://input"), true);

        $stmt = $pdo->prepare(
            "INSERT INTO logs (session_id, log_type, timestamp, data)
             VALUES (:session_id, :log_type, NOW(), :data)"
        );

        $stmt->execute([
            'session_id' => $body['sessionId'] ?? 'unknown',
            'log_type'   => $body['type'] ?? 'unknown',
            'data'       => json_encode($body)
        ]);

        http_response_code(201);
        echo json_encode(["status" => "created"]);
        break;

    // PUT /api/logs/{id}
    case 'PUT':
        if (!$id) {
            http_response_code(400);
            echo json_encode(["error" => "ID required"]);
            break;
        }

        $body = json_decode(file_get_contents("php://input"), true);

        $stmt = $pdo->prepare(
            "UPDATE logs SET data = :data WHERE id = :id"
        );
        $stmt->execute([
            'data' => json_encode($body),
            'id' => $id
        ]);

        echo json_encode(["status" => "updated"]);
        break;

    // DELETE /api/logs/{id}
    case 'DELETE':
        if (!$id) {
            http_response_code(400);
            echo json_encode(["error" => "ID required"]);
            break;
        }

        $stmt = $pdo->prepare("DELETE FROM logs WHERE id = :id");
        $stmt->execute(['id' => $id]);

        echo json_encode(["status" => "deleted"]);
        break;

    default:
        http_response_code(405);
        echo json_encode(["error" => "Method not allowed"]);
}
