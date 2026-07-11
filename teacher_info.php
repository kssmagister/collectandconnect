<?php
// Oeffentlich: liefert nur den Anzeigenamen zu einem gueltigen Lehrer-Code,
// damit die Formulare "Feedback fuer <Name>" anzeigen koennen.
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
if ($code === '') { echo json_encode(['success' => false]); exit; }

try {
    $conn = db();
    $stmt = $conn->prepare("SELECT name FROM teachers WHERE code = ? LIMIT 1");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    echo json_encode($row ? ['success' => true, 'name' => $row['name']] : ['success' => false]);
} catch (\Throwable $e) {
    // z.B. wenn die teachers-Tabelle (noch) fehlt -> sauber statt 500
    echo json_encode(['success' => false]);
}
