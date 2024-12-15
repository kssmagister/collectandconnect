<?php
require_once 'config.php';

// Verbindung erstellen
$conn = new mysqli($servername, $username, $password, $dbname);

// Verbindung überprüfen
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$auswahl = $_POST['auswahl'];
$texteingabe = $_POST['texteingabe'];

$sql = "INSERT INTO memoranda (auswahl, texteingabe) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $auswahl, $texteingabe);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
