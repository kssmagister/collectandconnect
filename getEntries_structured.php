<?php
require_once 'config.php'; // startet Session

header('Content-Type: application/json');

// Nur eingeloggte Admins duerfen Schuelerdaten abrufen (Datenschutz)
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
    exit;
}

// Verbindung erstellen
$conn = new mysqli($servername, $username, $password, $dbname);

// Verbindung überprüfen
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen: ' . $conn->connect_error]);
    exit;
}

// Filter-Parameter aus GET
$filterClass = isset($_GET['class']) ? trim($_GET['class']) : '';
$filterNickname = isset($_GET['nickname']) ? trim($_GET['nickname']) : '';
$sortOrder = isset($_GET['sort']) && $_GET['sort'] === 'asc' ? 'ASC' : 'DESC';

// SQL-Query mit optionalen Filtern
$sql = "SELECT id, nickname, auswahl, was, wann, warum, folgen, beispiel, timestamp 
        FROM memoranda_structured 
        WHERE 1=1";

$params = [];
$types = '';

// Filter nach Klasse
if (!empty($filterClass)) {
    $sql .= " AND auswahl = ?";
    $params[] = $filterClass;
    $types .= 's';
}

// Filter nach Nickname
if (!empty($filterNickname)) {
    $sql .= " AND nickname LIKE ?";
    $params[] = '%' . $filterNickname . '%';
    $types .= 's';
}

// Sortierung
$sql .= " ORDER BY timestamp $sortOrder";

// Prepared Statement
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Vorbereiten der Abfrage: ' . $conn->error]);
    exit;
}

// Parameter binden, falls vorhanden
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$entries = [];
while ($row = $result->fetch_assoc()) {
    $entries[] = [
        'id' => $row['id'],
        'nickname' => $row['nickname'],
        'auswahl' => $row['auswahl'],
        'was' => $row['was'],
        'wann' => $row['wann'],
        'warum' => $row['warum'],
        'folgen' => $row['folgen'],
        'beispiel' => $row['beispiel'],
        'timestamp' => $row['timestamp']
    ];
}

echo json_encode([
    'success' => true,
    'count' => count($entries),
    'data' => $entries
]);

$stmt->close();
$conn->close();
?>