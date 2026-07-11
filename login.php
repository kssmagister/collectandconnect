<?php
// Admin-Login: bcrypt-Passwortpruefung + einfaches Rate-Limit gegen Brute-Force.
// Das Rate-Limit ist "fail-open": laesst sich die Zaehl-Tabelle nicht nutzen,
// wird die Bremse uebersprungen, damit der Login nie ganz blockiert.
require_once __DIR__ . '/db.php'; // config.php (Session/.env) + db()/Helfer

header('Content-Type: application/json');

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$conn = db();

$maxAttempts = 8;
$windowMin   = 15; // fest, keine Benutzereingabe

// Tabelle bei Bedarf selbst anlegen (Ergebnis bewusst ignoriert).
@$conn->query(
    "CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// ── Rate-Limit: Fehlversuche pro IP im Zeitfenster zaehlen ─────────────
$fails = 0;
$throttleOk = false;
$stmt = @$conn->prepare(
    "SELECT COUNT(*) AS c FROM login_attempts
     WHERE ip = ? AND created_at > (NOW() - INTERVAL $windowMin MINUTE)"
);
if ($stmt) {
    $throttleOk = true;
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $fails = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
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
$userOk = hash_equals((string) $adminUsername, $username);
if (!empty($adminPasswordHash)) {
    $passOk = password_verify($password, $adminPasswordHash);
} else {
    $passOk = hash_equals((string) $adminPassword, $password);
}

if ($userOk && $passOk) {
    // Erfolg: Fehlversuche dieser IP loeschen, Session erneuern
    if ($throttleOk && ($del = @$conn->prepare("DELETE FROM login_attempts WHERE ip = ?"))) {
        $del->bind_param('s', $ip);
        $del->execute();
        $del->close();
    }
    session_regenerate_id(true); // Session-Fixation verhindern
    $_SESSION['loggedin'] = true;
    csrf_token(); // CSRF-Token fuer diese Session erzeugen
    echo json_encode(['success' => true]);
} else {
    // Fehlversuch protokollieren; kleine Verzoegerung bremst Skripte zusaetzlich
    if ($throttleOk && ($ins = @$conn->prepare("INSERT INTO login_attempts (ip) VALUES (?)"))) {
        $ins->bind_param('s', $ip);
        $ins->execute();
        $ins->close();
        @$conn->query("DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 1 DAY)");
    }
    usleep(300000); // 0,3 s
    echo json_encode(['success' => false, 'message' => 'Invalid username or password']);
}

$conn->close();
