<?php
// Speichert die persoenliche Klassenauswahl der eingeloggten Lehrperson.
// Leere Auswahl -> NULL = alle Klassen (wie bisher).
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
require_login();
require_csrf();

if (($_POST['action'] ?? '') !== 'save') {
    echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion.']);
    exit;
}

$me = current_teacher_id();
$sent = $_POST['classes'] ?? [];
if (!is_array($sent)) { $sent = []; }

// Nur gueltige Klassen aus der Stammliste uebernehmen (Reihenfolge beibehalten).
$valid = array_values(array_intersect(all_classes_flat(), $sent));
$json  = $valid ? json_encode($valid, JSON_UNESCAPED_UNICODE) : null;

$conn = db();
$stmt = $conn->prepare("UPDATE teachers SET classes = ? WHERE id = ?");
$stmt->bind_param('si', $json, $me);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

echo json_encode($ok
    ? ['success' => true, 'count' => count($valid)]
    : ['success' => false, 'message' => 'Fehler beim Speichern.']);
