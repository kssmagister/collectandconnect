<?php
require_once 'config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

// API-Key Authentifizierung: ausschliesslich per Header (nie per GET-Parameter,
// sonst landet der Key in Server-Logs und Browser-History).
$valid_api_key = getenv('PYTHON_API_KEY');
$provided_api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($valid_api_key === false || $valid_api_key === '' || !hash_equals($valid_api_key, $provided_api_key)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Invalid API Key']);
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Parameter
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 1000;
$format = $_GET['format'] ?? 'combined';

// Daten abfragen
$sql = "SELECT id, nickname, auswahl, was, wann, warum, folgen, beispiel, timestamp 
        FROM memoranda_structured 
        ORDER BY timestamp DESC 
        LIMIT ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $limit);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    if ($format === 'combined') {
        $row['texteingabe'] = sprintf(
            "Was: %s | Wann: %s | Warum: %s | Folgen: %s | Beispiel: %s",
            $row['was'],
            $row['wann'],
            $row['warum'],
            $row['folgen'],
            $row['beispiel'] ?? ''
        );
    }
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'meta' => [
        'total_entries' => count($data),
        'timestamp' => date('c')
    ],
    'data' => $data
]);

$stmt->close();
$conn->close();
?>