<?php
// Warteschlange fuer angeforderte Lektions-Auswertungen.
// Authentifizierung per API-Key (X-API-Key), wie api_structured_data.php.
//   GET  -> Liste offener Anforderungen [{lesson_code, title, teacher_code}]
//   POST action=done&lesson=CODE -> Anforderung als erledigt markieren
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: X-API-Key');

$valid = getenv('PYTHON_API_KEY');
$given = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($valid === false || $valid === '' || !hash_equals($valid, $given)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Invalid API Key']);
    exit;
}

try {
    $conn = db();

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $action = $_POST['action'] ?? '';
        $lesson = trim($_POST['lesson'] ?? '');
        if ($action === 'done' && $lesson !== '') {
            $stmt = $conn->prepare("UPDATE lessons SET analysis_requested = 0 WHERE code = ?");
            $stmt->bind_param('s', $lesson);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
        }
    } else {
        $sql = "SELECT l.code AS lesson_code, l.title, t.code AS teacher_code
                FROM lessons l
                JOIN teachers t ON t.id = l.teacher_id
                WHERE l.analysis_requested = 1
                ORDER BY l.analysis_requested_at ASC";
        $res = $conn->query($sql);
        $data = [];
        while ($row = $res->fetch_assoc()) { $data[] = $row; }
        echo json_encode(['success' => true, 'data' => $data]);
    }

    $conn->close();
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Fehler']);
}
