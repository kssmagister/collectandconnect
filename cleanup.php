<?php
// EINMALIGES Aufraeumskript: entfernt verwaiste Altdateien des frueheren Systems
// sowie die Fehlablage (verschachtelter www-Ordner) aus dem ersten Deploy-Versuch.
// Login-geschuetzt. Wird nach Gebrauch wieder aus dem Repo entfernt.
require_once __DIR__ . '/config.php'; // startet Session + laedt .env

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['loggedin'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Nicht eingeloggt. Bitte zuerst im Admin anmelden (login.html), dann diese Seite erneut aufrufen.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Feste Whitelist – nur diese Dateien werden geloescht (keine Wildcards).
$orphans = [
    'collect.html', 'submit_collect.php', 'getEntries.php', 'getEntries_structured.php',
    'getEntries_memoranda_structured.php', 'clearDB.php', 'clearDB_memoranda_structured.php',
    'download.php', 'download_text.php', 'view.html', 'admin_structured.html',
    'db_config.php', 'checkLogin.php',
];

$deleted = [];
$missing = [];
$failed = [];
foreach ($orphans as $f) {
    $path = __DIR__ . '/' . $f;
    if (!file_exists($path)) { $missing[] = $f; continue; }
    if (@unlink($path)) { $deleted[] = $f; } else { $failed[] = $f; }
}

// Verschachtelten Fehlablage-Ordner rekursiv entfernen.
function rrmdir($dir) {
    if (!is_dir($dir)) return;
    foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
        $p = $dir . '/' . $item;
        is_dir($p) ? rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}
$strayDir = __DIR__ . '/www';
$strayExisted = is_dir($strayDir);
if ($strayExisted) { rrmdir($strayDir); }

echo json_encode([
    'success'           => true,
    'deleted'           => $deleted,
    'missing'           => $missing,   // war schon weg – ok
    'failed'            => $failed,    // sollte leer sein
    'stray_www_removed' => $strayExisted && !is_dir($strayDir),
    'hinweis'           => 'Aufraeumen abgeschlossen. Bitte cleanup.php jetzt wieder entfernen lassen.',
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
