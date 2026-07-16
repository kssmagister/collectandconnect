<?php
// Oeffentlich: liefert nur den Anzeigenamen zu einem gueltigen Lehrer-Code,
// damit die Formulare "Feedback fuer <Name>" anzeigen koennen.
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
if ($code === '') { echo json_encode(['success' => false]); exit; }

try {
    $conn = db();
    $stmt = $conn->prepare("SELECT name, classes FROM teachers WHERE code = ? LIMIT 1");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();

    if (!$row) { echo json_encode(['success' => false]); exit; }

    // Persoenliche Klassenauswahl (leer/NULL -> null = alle Klassen anzeigen)
    $classes = $row['classes'] ? json_decode($row['classes'], true) : null;
    echo json_encode([
        'success' => true,
        'name'    => $row['name'],
        'classes' => (is_array($classes) && $classes) ? $classes : null,
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    // z.B. wenn die teachers-Tabelle (noch) fehlt -> sauber statt 500
    echo json_encode(['success' => false]);
}
