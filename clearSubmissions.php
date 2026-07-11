<?php
// Loescht Antworten (nur eingeloggte Admins). Optional nur einen Typ via form_type.
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
require_login();
require_csrf(); // Loeschen nur mit gueltigem CSRF-Token

$formType = isset($_POST['form_type']) ? trim($_POST['form_type']) : '';

$conn = db();

if ($formType !== '') {
    $stmt = $conn->prepare('DELETE FROM submissions WHERE form_type = ?');
    $stmt->bind_param('s', $formType);
    $ok = $stmt->execute();
    $stmt->close();
} else {
    $ok = $conn->query('DELETE FROM submissions') !== false;
}

echo json_encode($ok
    ? ['success' => true, 'message' => 'Daten geloescht']
    : ['success' => false, 'message' => 'Fehler beim Loeschen']);

$conn->close();
