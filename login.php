<?php
// Admin-Login: bcrypt-Passwortpruefung + einfaches Rate-Limit gegen Brute-Force.
// Das Rate-Limit ist "fail-open": geht die Zaehl-Tabelle nicht (fehlende Rechte,
// Tabelle nicht vorhanden, mysqli-Exception ab PHP 8.1), wird die Bremse einfach
// uebersprungen, damit der Login nie ganz blockiert.
//
// WICHTIG: Die Formular-Eingaben heissen bewusst $inUser/$inPass und NICHT
// $username/$password – letztere sind in config.php die DB-Zugangsdaten, die db()
// per global nutzt. Ein Ueberschreiben wuerde die DB-Verbindung kaputt machen.
require_once __DIR__ . '/db.php'; // config.php (Session/.env) + db()/Helfer

header('Content-Type: application/json');

$inUser = $_POST['username'] ?? '';
$inPass = $_POST['password'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$conn = db();

$maxAttempts = 8;
$windowMin   = 15; // fest, keine Benutzereingabe

// Best-effort: Tabelle anlegen. Fehlt das Recht, wird die Exception geschluckt.
try {
    $conn->query(
        "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
} catch (\Throwable $e) { /* egal – Rate-Limit ist optional */ }

// ── Rate-Limit: Fehlversuche pro IP im Zeitfenster zaehlen ─────────────
$fails = 0;
$throttleOk = false;
try {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c FROM login_attempts
         WHERE ip = ? AND created_at > (NOW() - INTERVAL $windowMin MINUTE)"
    );
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $fails = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    $throttleOk = true;
} catch (\Throwable $e) {
    $throttleOk = false; // Bremse deaktiviert, Login funktioniert weiter
}

if ($throttleOk && $fails >= $maxAttempts) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Zu viele Fehlversuche. Bitte in etwa ' . $windowMin . ' Minuten erneut versuchen.'
    ]);
    exit;
}

// ── Zugangsdaten pruefen: bevorzugt bcrypt-Hash, sonst Klartext-Fallback ─
$userOk = hash_equals((string) $adminUsername, $inUser);
if (!empty($adminPasswordHash)) {
    $passOk = password_verify($inPass, $adminPasswordHash);
} else {
    $passOk = hash_equals((string) $adminPassword, $inPass);
}

if ($userOk && $passOk) {
    // Erfolg: Fehlversuche dieser IP loeschen (best-effort), Session erneuern
    if ($throttleOk) {
        try {
            $del = $conn->prepare("DELETE FROM login_attempts WHERE ip = ?");
            $del->bind_param('s', $ip);
            $del->execute();
            $del->close();
        } catch (\Throwable $e) { /* egal */ }
    }
    session_regenerate_id(true); // Session-Fixation verhindern
    $_SESSION['loggedin'] = true;
    csrf_token(); // CSRF-Token fuer diese Session erzeugen
    echo json_encode(['success' => true]);
} else {
    // Fehlversuch protokollieren (best-effort); kleine Verzoegerung bremst Skripte
    if ($throttleOk) {
        try {
            $ins = $conn->prepare("INSERT INTO login_attempts (ip) VALUES (?)");
            $ins->bind_param('s', $ip);
            $ins->execute();
            $ins->close();
            $conn->query("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)");
        } catch (\Throwable $e) { /* egal */ }
    }
    usleep(300000); // 0,3 s
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
}

$conn->close();
