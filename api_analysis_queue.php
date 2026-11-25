<?php
// Warteschlange fuer angeforderte Lektions-Auswertungen.
// Authentifizierung per API-Key (X-API-Key), wie api_structured_data.php.
//   GET  -> Liste offener Anforderungen [{lesson_code, title, teacher_code}]
//   POST action=done&lesson=CODE[&report_url=...] -> Anforderung als erledigt markieren
//   POST action=failed&lesson=CODE[&error=...]     -> Anforderung fehlgeschlagen (Grund anzeigen)
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
            // Optionaler Link zum fertigen Bericht (DRP-Web-Frontend). Ungueltige
            // Werte (kein URL) werden ignoriert - die Anforderung gilt trotzdem als erledigt.
            $reportUrl = trim($_POST['report_url'] ?? '');
            if ($reportUrl !== '' && !filter_var($reportUrl, FILTER_VALIDATE_URL)) {
                $reportUrl = '';
            }
            if ($reportUrl !== '') {
                $stmt = $conn->prepare(
                    "UPDATE lessons SET analysis_requested = 0, last_report_url = ?, last_report_at = NOW(),
                            last_error = NULL, last_error_at = NULL WHERE code = ?"
                );
                $stmt->bind_param('ss', $reportUrl, $lesson);
            } else {
                $stmt = $conn->prepare("UPDATE lessons SET analysis_requested = 0, last_error = NULL, last_error_at = NULL WHERE code = ?");
                $stmt->bind_param('s', $lesson);
            }
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true]);
        } elseif ($action === 'failed' && $lesson !== '') {
            // Auswertung fehlgeschlagen: Markierung loeschen (kein Endlos-Retry per Cron)
            // und den Grund fuer die Anzeige in lessons.php speichern.
            $error = trim($_POST['error'] ?? '');
            if ($error === '') { $error = 'Unbekannter Fehler'; }
            $error = mb_substr($error, 0, 500);
            $stmt = $conn->prepare(
                "UPDATE lessons SET analysis_requested = 0, last_error = ?, last_error_at = NOW() WHERE code = ?"
            );
            $stmt->bind_param('ss', $error, $lesson);
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
                WHERE l.analysis_requested = 1 AND l.is_hidden = 0
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
