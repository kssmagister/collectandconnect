<?php
session_start();
require_once 'config.php';

// Überprüfen, ob der Benutzer eingeloggt ist
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.html');
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);

// Verbindung überprüfen
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

header('Content-Type: application/json');

$query = "SELECT id, auswahl, texteingabe, timestamp FROM memoranda ORDER BY timestamp DESC";
$result = $conn->query($query);

$entries = [];

while($row = $result->fetch_assoc()) {
    $entries[] = $row;
}

echo json_encode($entries);

$conn->close();
?>
