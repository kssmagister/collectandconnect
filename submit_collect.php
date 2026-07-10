<?php
require_once 'config.php';

header('Content-Type: application/json');

// Verbindung erstellen
$conn = new mysqli($servername, $username, $password, $dbname);

// Verbindung überprüfen
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
    exit;
}

// Daten aus POST empfangen und validieren
$nickname = isset($_POST['nickname']) ? trim($_POST['nickname']) : '';
$auswahl = isset($_POST['auswahl']) ? trim($_POST['auswahl']) : '';
$was = isset($_POST['was']) ? trim($_POST['was']) : '';
$wann = isset($_POST['wann']) ? trim($_POST['wann']) : '';
$warum = isset($_POST['warum']) ? trim($_POST['warum']) : '';
$folgen = isset($_POST['folgen']) ? trim($_POST['folgen']) : '';
$beispiel = isset($_POST['beispiel']) ? trim($_POST['beispiel']) : '';

// Validierung der Pflichtfelder
if (empty($nickname) || empty($auswahl)) {
    echo json_encode(['success' => false, 'message' => 'Bitte fülle Nickname und Auswahl aus']);
    exit;
}

// SQL-Statement mit prepared statement für Sicherheit
$sql = "INSERT INTO memoranda_structured (nickname, auswahl, was, wann, warum, folgen, beispiel) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Vorbereiten der Anfrage']);
    exit;
}

$stmt->bind_param("sssssss", $nickname, $auswahl, $was, $wann, $warum, $folgen, $beispiel);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Daten erfolgreich gespeichert', 'id' => $stmt->insert_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Fehler beim Speichern: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>