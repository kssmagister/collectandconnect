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

// Alle Daten aus der Datenbanktabelle "memoranda" holen
$query = "SELECT * FROM memoranda";
$result = $conn->query($query);

// Daten als CSV exportieren
header('Content-Type: text/csv');

$filename = date('d-m-Y_H:i') . '_daten.csv';
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputcsv($output, array('id', 'auswahl', 'texteingabe', 'timestamp'));  // Header

while($row = $result->fetch_assoc()) {
    fputcsv($output, $row);
}

fclose($output);
exit();
?>
