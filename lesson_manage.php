<?php
// Lektionen-Aktionen der eingeloggten Lehrperson (CSRF-geschuetzt): add / edit / delete / hide / request.
// Jede Lehrperson verwaltet NUR ihre eigenen Lektionen.
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
require_login();
require_csrf();

$conn = db();
$me = current_teacher_id();
$action = $_POST['action'] ?? '';

// Antworttool aus dem Formular: nur bekannte Typen, sonst NULL (= Schueler waehlen selbst).
function posted_form_type(): ?string {
    $ft = trim($_POST['form_type'] ?? '');
    return array_key_exists($ft, form_types()) ? $ft : null;
}

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
    $title    = trim($_POST['title'] ?? '');
    $question = trim($_POST['question'] ?? '');
    if ($title === '') {
        echo json_encode(['success' => false, 'message' => 'Titel noetig.']); exit;
    }
    $q = ($question === '') ? null : $question;
    $ft = posted_form_type();
    $code = gen_lesson_code($conn);
    $stmt = $conn->prepare("INSERT INTO lessons (teacher_id, code, title, feedback_question, form_type) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issss', $me, $code, $title, $q, $ft);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode($ok ? ['success' => true, 'code' => $code] : ['success' => false, 'message' => 'Fehler.']);
    exit;
}

if ($action === 'edit') {
    // Titel, Frage/Aufgabe und Antworttool aendern (nur eigene Lektion).
    $id       = (int) ($_POST['id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $question = trim($_POST['question'] ?? '');
    if ($id <= 0 || $title === '') {
        echo json_encode(['success' => false, 'message' => 'Titel noetig.']); exit;
    }
    $q = ($question === '') ? null : $question;
    $ft = posted_form_type();
    $stmt = $conn->prepare("UPDATE lessons SET title = ?, feedback_question = ?, form_type = ? WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param('sssii', $title, $q, $ft, $id, $me);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode($ok ? ['success' => true] : ['success' => false, 'message' => 'Fehler.']);
    exit;
}

if ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Ungueltig.']); exit; }
    $conn->begin_transaction();
    try {
        // Lektion samt zugehoerigen Antworten loeschen (nur eigene)
        $u = $conn->prepare("DELETE FROM submissions WHERE lesson_id = ? AND teacher_id = ?");
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

if ($action === 'hide') {
    // Lektion (und ihre Antworten) aus Admin/Beamer/API aus- bzw. wieder einblenden.
    $id     = (int) ($_POST['id'] ?? 0);
    $hidden = empty($_POST['hidden']) ? 0 : 1;
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Ungueltig.']); exit; }
    $stmt = $conn->prepare("UPDATE lessons SET is_hidden = ? WHERE id = ? AND teacher_id = ?");
    $stmt->bind_param('iii', $hidden, $id, $me);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode($ok ? ['success' => true] : ['success' => false, 'message' => 'Fehler.']);
    exit;
}

if ($action === 'request') {
    // Auswertung dieser Lektion anfordern (der Ubuntu-Server holt sie ab).
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['success' => false, 'message' => 'Ungueltig.']); exit; }
    $stmt = $conn->prepare(
        "UPDATE lessons SET analysis_requested = 1, analysis_requested_at = NOW(),
                last_error = NULL, last_error_at = NULL
         WHERE id = ? AND teacher_id = ?"
    );
    $stmt->bind_param('ii', $id, $me);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode($ok ? ['success' => true] : ['success' => false, 'message' => 'Fehler.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion.']);
