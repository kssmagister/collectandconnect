<?php
// Lektionen-Aktionen der eingeloggten Lehrperson (CSRF-geschuetzt): add / delete.
// Jede Lehrperson verwaltet NUR ihre eigenen Lektionen.
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
require_login();
require_csrf();

$conn = db();
$me = current_teacher_id();
$action = $_POST['action'] ?? '';

function gen_lesson_code(mysqli $conn): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = '';
        for ($i = 0; $i < 5; $i++) { $code .= $alphabet[random_int(0, strlen($alphabet) - 1)]; }
        $s = $conn->prepare("SELECT 1 FROM lessons WHERE code = ?");
        $s->bind_param('s', $code); $s->execute();
        $exists = (bool) $s->get_result()->fetch_row();
        $s->close();
    } while ($exists);
    return $code;
}

if ($action === 'add') {
    $title = trim($_POST['title'] ?? '');
    if ($title === '') {
        echo json_encode(['success' => false, 'message' => 'Titel noetig.']); exit;
    }
    $code = gen_lesson_code($conn);
    $stmt = $conn->prepare("INSERT INTO lessons (teacher_id, code, title) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $me, $code, $title);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode($ok ? ['success' => true, 'code' => $code] : ['success' => false, 'message' => 'Fehler.']);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Ungueltig.']); exit; }
    $conn->begin_transaction();
    try {
        // Zuordnung in Antworten loesen (Antworten selbst bleiben erhalten)
        $u = $conn->prepare("UPDATE submissions SET lesson_id = NULL WHERE lesson_id = ? AND teacher_id = ?");
        $u->bind_param('ii', $id, $me); $u->execute(); $u->close();
        $d = $conn->prepare("DELETE FROM lessons WHERE id = ? AND teacher_id = ?");
        $d->bind_param('ii', $id, $me); $d->execute(); $d->close();
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Fehler beim Loeschen.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion.']);
