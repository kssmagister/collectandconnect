<?php
// Liefert die Antworten der eingeloggten Lehrperson als JSON (inkl. Lektionstitel).
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
require_login();

$teacherId = current_teacher_id();
$formType = isset($_GET['form_type']) ? trim($_GET['form_type']) : '';
$klasse   = isset($_GET['klasse']) ? trim($_GET['klasse']) : '';
$nickname = isset($_GET['nickname']) ? trim($_GET['nickname']) : '';
$sort     = (isset($_GET['sort']) && $_GET['sort'] === 'asc') ? 'ASC' : 'DESC';

// Datentrennung: immer auf die eigene teacher_id einschraenken.
$sql = 'SELECT s.id, s.form_type, s.klasse, s.nickname, s.payload, s.created_at,
               s.lesson_id, l.title AS lesson_title
        FROM submissions s
        LEFT JOIN lessons l ON l.id = s.lesson_id
        WHERE s.teacher_id = ?';
$params = [$teacherId];
$types = 'i';

if ($formType !== '') { $sql .= ' AND s.form_type = ?'; $params[] = $formType; $types .= 's'; }
if ($klasse !== '')   { $sql .= ' AND s.klasse = ?';    $params[] = $klasse;   $types .= 's'; }
if ($nickname !== '') { $sql .= ' AND s.nickname LIKE ?'; $params[] = '%' . $nickname . '%'; $types .= 's'; }

$sql .= " ORDER BY s.created_at $sort";

$conn = db();
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$entries = [];
while ($row = $result->fetch_assoc()) {
    $row['payload'] = json_decode($row['payload'], true);
    $entries[] = $row;
}

echo json_encode(['success' => true, 'count' => count($entries), 'data' => $entries]);

$stmt->close();
$conn->close();
