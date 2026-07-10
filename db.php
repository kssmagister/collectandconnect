<?php
// Zentrale Helfer: DB-Verbindung + Login-Pruefung.
// Vermeidet, dass jeder Endpunkt Verbindungsaufbau/Auth kopiert.

require_once __DIR__ . '/config.php'; // startet Session + laedt .env

function db(): mysqli {
    global $servername, $username, $password, $dbname;
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Datenbankverbindung fehlgeschlagen']);
        exit;
    }
    $conn->set_charset('utf8mb4'); // Umlaute korrekt speichern/lesen
    return $conn;
}

// Bricht mit 401 ab, wenn kein Admin eingeloggt ist.
function require_login(): void {
    if (empty($_SESSION['loggedin'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
        exit;
    }
}
