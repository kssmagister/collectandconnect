<?php
// Liefert gespeicherte Antworten als JSON (nur fuer eingeloggte Admins).
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
require_login();

$formType = isset($_GET['form_type']) ? trim($_GET['form_type']) : '';
$klasse   = isset($_GET['klasse']) ? trim($_GET['klasse']) : '';
$nickname = isset($_GET['nickname']) ? trim($_GET['nickname']) : '';
$sort     = (isset($_GET['sort']) && $_GET['sort'] === 'asc') ? 'ASC' : 'DESC';

$sql = 'SELECT id, form_type, klasse, nickname, payload, created_at FROM submissions WHERE 1=1';
$params = [];
$types = '';

if ($formType !== '') { $sql .= ' AND form_type = ?'; $params[] = $formType; $types .= 's'; }
if ($klasse !== '')   { $sql .= ' AND klasse = ?';    $params[] = $klasse;   $types .= 's'; }
if ($nickname !== '') { $sql .= ' AND nickname LIKE ?'; $params[] = '%' . $nickname . '%'; $types .= 's'; }

$sql .= " ORDER BY created_at $sort";

$conn = db();
$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$entries = [];
while ($row = $result->fetch_assoc()) {
    $row['payload'] = json_decode($row['payload'], true); // JSON -> Objekt
    $entries[] = $row;
}

echo json_encode(['success' => true, 'count' => count($entries), 'data' => $entries]);

$stmt->close();
$conn->close();
