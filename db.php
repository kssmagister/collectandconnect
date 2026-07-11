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

// ── CSRF ──────────────────────────────────────────────────────────────
// Erzeugt/liefert das CSRF-Token der aktuellen Session.
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Erzwingt ein gueltiges CSRF-Token (aus Header X-CSRF-TOKEN oder POST-Feld
// csrf_token). Bricht sonst mit 403 ab. Schuetzt zustandsaendernde Aktionen
// davor, von einer fremden Seite ueber das Session-Cookie ausgeloest zu werden.
function require_csrf(): void {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'CSRF-Token ungueltig']);
        exit;
    }
}
