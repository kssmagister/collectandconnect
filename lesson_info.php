<?php
// Oeffentlich: liefert den Lektionstitel zu einem Lektions-Code (fuer den
// Banner auf den Schueler-Seiten).
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

$code = isset($_GET['code']) ? trim($_GET['code']) : '';
if ($code === '') { echo json_encode(['success' => false]); exit; }

try {
    $conn = db();
    $stmt = $conn->prepare("SELECT title FROM lessons WHERE code = ? LIMIT 1");
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $conn->close();
    echo json_encode($row ? ['success' => true, 'title' => $row['title']] : ['success' => false]);
} catch (\Throwable $e) {
    echo json_encode(['success' => false]);
}
