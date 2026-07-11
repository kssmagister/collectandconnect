<?php
// Generischer Endpunkt fuer ALLE Fragetypen.
// Erwartet POST: code (Lehrer-Code), form_type, klasse, (nickname optional)
// + typ-spezifische Felder.
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// Erlaubte Formulartypen und ihre Felder.
$schemas = [
    'feedback'     => ['required' => ['text'],                              'optional' => []],
    'exit_ticket'  => ['required' => ['erkenntnis', 'sicherheit'],          'optional' => ['frage', 'hilfe']],
    'strukturiert' => ['required' => ['was', 'wann', 'warum', 'folgen'],    'optional' => ['beispiel']],
];

$code     = isset($_POST['code']) ? trim($_POST['code']) : '';
$formType = isset($_POST['form_type']) ? trim($_POST['form_type']) : '';
$klasse   = isset($_POST['klasse']) ? trim($_POST['klasse']) : '';
$nickname = isset($_POST['nickname']) ? trim($_POST['nickname']) : '';

if (!isset($schemas[$formType])) {
    echo json_encode(['success' => false, 'message' => 'Unbekannter Formulartyp']);
    exit;
}
if ($klasse === '') {
    echo json_encode(['success' => false, 'message' => 'Bitte eine Klasse waehlen']);
    exit;
}

$conn = db();

// Lehrer-Code aufloesen -> teacher_id
if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Kein Lehrer-Link. Bitte den Link deiner Lehrperson verwenden.']);
    exit;
}
$stmt = $conn->prepare("SELECT id FROM teachers WHERE code = ? LIMIT 1");
$stmt->bind_param('s', $code);
$stmt->execute();
$teacher = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$teacher) {
    echo json_encode(['success' => false, 'message' => 'Ungueltiger Lehrer-Link.']);
    exit;
}
$teacherId = (int) $teacher['id'];

// Optionaler Lektions-Code -> lesson_id (nur wenn er dieser Lehrperson gehoert).
// Ungueltiger Code: Antwort geht trotzdem durch (ohne Lektionszuordnung).
$lessonCode = isset($_POST['lesson']) ? trim($_POST['lesson']) : '';
$lessonId = null;
if ($lessonCode !== '') {
    $ls = $conn->prepare("SELECT id FROM lessons WHERE code = ? AND teacher_id = ? LIMIT 1");
    $ls->bind_param('si', $lessonCode, $teacherId);
    $ls->execute();
    $lrow = $ls->get_result()->fetch_assoc();
    $ls->close();
    if ($lrow) { $lessonId = (int) $lrow['id']; }
}

// Payload aus erlaubten Feldern zusammenbauen
$schema = $schemas[$formType];
$payload = [];
foreach ($schema['required'] as $field) {
    $value = isset($_POST[$field]) ? trim($_POST[$field]) : '';
    if ($value === '') {
        echo json_encode(['success' => false, 'message' => 'Bitte alle Pflichtfelder ausfuellen']);
        exit;
    }
    $payload[$field] = $value;
}
foreach ($schema['optional'] as $field) {
    $value = isset($_POST[$field]) ? trim($_POST[$field]) : '';
    if ($value !== '') { $payload[$field] = $value; }
}

// Sicherheits-Skala 1-5 pruefen
if ($formType === 'exit_ticket') {
    $s = filter_var($payload['sicherheit'], FILTER_VALIDATE_INT);
    if ($s === false || $s < 1 || $s > 5) {
        echo json_encode(['success' => false, 'message' => 'Sicherheit muss 1 bis 5 sein']);
        exit;
    }
    $payload['sicherheit'] = $s;
}

$payloadJson   = json_encode($payload, JSON_UNESCAPED_UNICODE);
$nicknameParam = ($nickname === '') ? null : $nickname;

$stmt = $conn->prepare(
    'INSERT INTO submissions (teacher_id, lesson_id, form_type, klasse, nickname, payload) VALUES (?, ?, ?, ?, ?, ?)'
);
$stmt->bind_param('iissss', $teacherId, $lessonId, $formType, $klasse, $nicknameParam, $payloadJson);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern']);
}

$stmt->close();
$conn->close();
