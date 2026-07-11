<?php
// Loescht die Antworten der eingeloggten Lehrperson (optional nur einen Typ).
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
require_login();
require_csrf(); // Loeschen nur mit gueltigem CSRF-Token

$teacherId = current_teacher_id();
$formType  = isset($_POST['form_type']) ? trim($_POST['form_type']) : '';

$conn = db();

if ($formType !== '') {
    $stmt = $conn->prepare("DELETE FROM submissions WHERE teacher_id = ? AND form_type = ?");
    $stmt->bind_param('is', $teacherId, $formType);
} else {
    $stmt = $conn->prepare("DELETE FROM submissions WHERE teacher_id = ?");
    $stmt->bind_param('i', $teacherId);
}
$ok = $stmt->execute();
$stmt->close();

echo json_encode($ok
    ? ['success' => true, 'message' => 'Daten geloescht']
    : ['success' => false, 'message' => 'Fehler beim Loeschen']);

$conn->close();
