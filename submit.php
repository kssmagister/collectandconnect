<?php
// Generischer Endpunkt fuer ALLE Fragetypen.
// Erwartet POST: form_type, klasse, (nickname optional) + typ-spezifische Felder.
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');

// Erlaubte Formulartypen und ihre Felder.
// Neuer Fragetyp spaeter = hier einen Eintrag ergaenzen (+ eine HTML-Seite). Sonst nichts.
$schemas = [
    'feedback' => [
        'required' => ['text'],
        'optional' => [],
    ],
    'exit_ticket' => [
        'required' => ['erkenntnis', 'sicherheit'],
        'optional' => ['frage', 'hilfe'],
    ],
    'strukturiert' => [
        'required' => ['was', 'wann', 'warum', 'folgen'],
        'optional' => ['beispiel'],
    ],
];

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

// Payload aus erlaubten Feldern zusammenbauen (nichts anderes wird gespeichert)
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
    if ($value !== '') {
        $payload[$field] = $value;
    }
}

// Spezialvalidierung: Sicherheits-Skala 1-5
if ($formType === 'exit_ticket') {
    $s = filter_var($payload['sicherheit'], FILTER_VALIDATE_INT);
    if ($s === false || $s < 1 || $s > 5) {
        echo json_encode(['success' => false, 'message' => 'Sicherheit muss 1 bis 5 sein']);
        exit;
    }
    $payload['sicherheit'] = $s; // als Zahl speichern
}

$conn = db();
$payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
$nicknameParam = ($nickname === '') ? null : $nickname;

$stmt = $conn->prepare(
    'INSERT INTO submissions (form_type, klasse, nickname, payload) VALUES (?, ?, ?, ?)'
);
$stmt->bind_param('ssss', $formType, $klasse, $nicknameParam, $payloadJson);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern']);
}

$stmt->close();
$conn->close();
