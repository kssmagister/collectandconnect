<?php
// Loescht die Antworten der eingeloggten Lehrperson (optional nur einen Typ).
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
require_login();
require_csrf(); // Loeschen nur mit gueltigem CSRF-Token

$teacherId = current_teacher_id();
$formType  = isset($_POST['form_type']) ? trim($_POST['form_type']) : '';

$conn = db();

// Auswahl von Lektionen (kommagetrennte IDs; 0 = Antworten ohne Lektion):
// loescht nur deren Antworten (die Lektion selbst bleibt bestehen).
$lessonsRaw = isset($_POST['lessons']) ? trim($_POST['lessons']) : '';
if ($lessonsRaw !== '') {
    $ids = array_values(array_unique(array_map('intval', explode(',', $lessonsRaw))));
    $withNull = in_array(0, $ids, true);
    $ids = array_values(array_filter($ids, function ($i) { return $i > 0; }));
    $cond = [];
    $params = [$teacherId];
    $types = 'i';
    if ($ids) {
        $cond[] = 'lesson_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        foreach ($ids as $i) { $params[] = $i; $types .= 'i'; }
    }
    if ($withNull) { $cond[] = 'lesson_id IS NULL'; }
    if (!$cond) { echo json_encode(['success' => false, 'message' => 'Keine Lektion gewaehlt']); exit; }
    $stmt = $conn->prepare('DELETE FROM submissions WHERE teacher_id = ? AND (' . implode(' OR ', $cond) . ')');
    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $n = $stmt->affected_rows;
    $stmt->close();
    echo json_encode($ok
        ? ['success' => true, 'message' => 'Daten geloescht', 'deleted' => $n]
        : ['success' => false, 'message' => 'Fehler beim Loeschen']);
    $conn->close();
    exit;
}

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
