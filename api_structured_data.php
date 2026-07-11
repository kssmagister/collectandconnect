<?php
// Read-only JSON-API fuer externe Auswertung (z.B. Heim-Ubuntu-Server + KI).
// Authentifizierung ausschliesslich per Header X-API-Key (nie per GET-Parameter,
// sonst landet der Key in Logs/History).
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: X-API-Key');

$valid_api_key    = getenv('PYTHON_API_KEY');
$provided_api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($valid_api_key === false || $valid_api_key === '' || !hash_equals($valid_api_key, $provided_api_key)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Invalid API Key']);
    exit;
}

// Parameter
$formType = isset($_GET['form_type']) ? trim($_GET['form_type']) : '';
$limit    = isset($_GET['limit']) ? max(1, min(10000, intval($_GET['limit']))) : 1000;
// Inkrementeller Abruf: nur Eintraege neuer als dieser Zeitstempel (ISO/MySQL-Format)
$since    = isset($_GET['since']) ? trim($_GET['since']) : '';
// Optional: nur die Daten einer Lehrperson (per Code). Ohne Angabe: alle.
$teacher  = isset($_GET['teacher']) ? trim($_GET['teacher']) : '';

$sql = 'SELECT id, teacher_id, form_type, klasse, nickname, payload, created_at FROM submissions WHERE 1=1';
$params = [];
$types = '';

if ($teacher !== '')  { $sql .= ' AND teacher_id = (SELECT id FROM teachers WHERE code = ?)'; $params[] = $teacher; $types .= 's'; }
if ($formType !== '') { $sql .= ' AND form_type = ?'; $params[] = $formType; $types .= 's'; }
if ($since !== '')    { $sql .= ' AND created_at > ?'; $params[] = $since;    $types .= 's'; }

$sql .= ' ORDER BY created_at DESC LIMIT ?';
$params[] = $limit;
$types .= 'i';

$conn = db();
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $payload = json_decode($row['payload'], true) ?: [];
    // Fertiger Fliesstext aus allen Antwortteilen – praktisch als KI-Input
    $parts = [];
    foreach ($payload as $key => $value) {
        $parts[] = ucfirst($key) . ': ' . $value;
    }
    $data[] = [
        'id'         => (int) $row['id'],
        'teacher_id' => (int) $row['teacher_id'],
        'form_type'  => $row['form_type'],
        'klasse'     => $row['klasse'],
        'nickname'   => $row['nickname'],
        'payload'    => $payload,
        'text'       => implode(' | ', $parts),
        'created_at' => $row['created_at'],
    ];
}

echo json_encode([
    'success' => true,
    'meta'    => ['total_entries' => count($data), 'timestamp' => date('c')],
    'data'    => $data,
], JSON_UNESCAPED_UNICODE);

$stmt->close();
$conn->close();
