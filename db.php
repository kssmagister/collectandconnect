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

// Bricht mit 401 ab, wenn niemand eingeloggt ist.
function require_login(): void {
    if (empty($_SESSION['loggedin'])) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Nicht eingeloggt']);
        exit;
    }
}

// ID der eingeloggten Lehrperson (fuer Daten-Trennung).
function current_teacher_id(): int {
    return (int) ($_SESSION['teacher_id'] ?? 0);
}

// Bricht mit 403 ab, wenn die eingeloggte Person kein Admin ist.
function require_admin(): void {
    require_login();
    if (empty($_SESSION['is_admin'])) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Nur fuer Administratoren']);
        exit;
    }
}

// ── Klassen ───────────────────────────────────────────────────────────
// Stammliste aller Klassen (Gruppen wie in den Formularen). Einzige Quelle
// der Wahrheit fuer die serverseitig gerenderten Auswahlfelder.
function all_classes(): array {
    return [
        'FMS'      => ['1FMS', '2FMS', '3FMS'],
        'GYM-G'    => ['1GYM-G', '2GYM-G', '3GYM-G', '4GYM-G'],
        'WMS/IMS'  => ['1WMS/IMS', '2WMS/IMS', '3WMS/IMS'],
        'Sonstige' => ['1sG','LatInt', 'efG', 'ffGR', 'EXTRA'],
    ];
}

// Flache Liste aller gueltigen Klassennamen.
function all_classes_flat(): array {
    return array_merge(...array_values(all_classes()));
}

// Persoenliche Klassenauswahl einer Lehrperson. Leeres Array = alle Klassen.
function teacher_classes(mysqli $conn, int $teacherId): array {
    $stmt = $conn->prepare("SELECT classes FROM teachers WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $teacherId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $list = ($row && $row['classes']) ? json_decode($row['classes'], true) : null;
    return is_array($list) ? $list : [];
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
