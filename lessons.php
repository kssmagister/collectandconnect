<?php
// Lektionen der eingeloggten Lehrperson (nicht nur Admin).
require_once __DIR__ . '/db.php';
if (empty($_SESSION['loggedin'])) { header('Location: login.html'); exit; }
$csrfToken   = csrf_token();
$teacherCode = $_SESSION['teacher_code'] ?? '';
$me = current_teacher_id();

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
      . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$conn = db();
$lessons = [];
$stmt = $conn->prepare("SELECT id, code, title, feedback_question, created_at, analysis_requested FROM lessons WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->bind_param('i', $me);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) { $lessons[] = $row; }
$stmt->close();
$conn->close();

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo h($csrfToken); ?>">
  <title>Lektionen – KSS COLLECT &amp; CONNECT</title>
  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
  <style>
    .container { max-width:1000px; }
    .table td, .table th { vertical-align:middle; font-size:13px; }
    .link-box { font-size:12px; color:#495057; word-break:break-all; }
    .code-chip { background:#495057; color:#fff; padding:0.15rem 0.5rem; border-radius:10px; font-weight:600; font-size:12px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
      <h1 class="mb-0">Meine Lektionen</h1>
      <div>
        <button class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#helpModal">❓ Hilfe</button>
        <a href="admin.php" class="btn btn-secondary btn-sm">← Admin</a>
      </div>
    </div>

    <div class="form-section">
      <h5>Neue Lektion</h5>
      <form id="addForm" class="form-row align-items-end">
        <div class="col-md-5 mb-2"><label>Titel der Lektion</label><input class="form-control form-control-sm" id="addTitle" placeholder="z.B. Ablativus Absolutus – Einführung" required></div>
        <div class="col-md-5 mb-2"><label>Feedback-Frage <span class="text-muted">(optional)</span></label><input class="form-control form-control-sm" id="addQuestion" placeholder="z.B. Beschreibe die Stunde in 3 Wörtern"></div>
        <div class="col-md-2 mb-2"><button class="btn btn-primary btn-sm btn-block" type="submit">Anlegen</button></div>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>Lektion &amp; Feedback-Frage</th><th>Code</th><th>Link für diese Lektion</th><th>Auswertung</th><th style="width:1%"></th></tr></thead>
        <tbody>
        <?php if (!$lessons): ?>
          <tr><td colspan="5" class="text-center text-muted">Noch keine Lektionen. Lege oben eine an.</td></tr>
        <?php endif; ?>
        <?php foreach ($lessons as $l):
            $link = $base . '/?t=' . rawurlencode($teacherCode) . '&l=' . rawurlencode($l['code']); ?>
          <tr data-id="<?php echo (int) $l['id']; ?>">
            <td style="min-width:260px">
              <input class="form-control form-control-sm edit-title mb-1" value="<?php echo h($l['title']); ?>" placeholder="Titel">
              <input class="form-control form-control-sm edit-question" value="<?php echo h($l['feedback_question']); ?>" placeholder="Feedback-Frage (optional)">
            </td>
            <td><span class="code-chip"><?php echo h($l['code']); ?></span></td>
            <td>
              <span class="link-box"><?php echo h($link); ?></span>
              <button class="btn btn-link btn-sm p-0 copy-link" data-link="<?php echo h($link); ?>">kopieren</button>
            </td>
            <td>
              <?php if (!empty($l['analysis_requested'])): ?>
                <span class="badge badge-warning">⏳ angefordert</span>
              <?php else: ?>
                <button class="btn btn-outline-info btn-sm act-req">KI-Auswertung anfordern</button>
              <?php endif; ?>
            </td>
            <td class="text-nowrap">
              <button class="btn btn-outline-primary btn-sm act-save">Speichern</button>
              <button class="btn btn-outline-danger btn-sm act-del">Löschen</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal fade" id="helpModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Lektionen</h5>
      <button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body" style="font-size:14px;">
      <ul class="pl-3 mb-0">
        <li>Lege pro Unterrichtsstunde eine Lektion an (z.B. das Thema).</li>
        <li>Teile den <strong>Lektions-Link</strong> mit deiner Klasse (statt des allgemeinen Links). Antworten werden dann dieser Lektion zugeordnet.</li>
        <li>Die <strong>Feedback-Frage</strong> (optional) wird den Schülern im Feedback-Formular angezeigt – z.B. „Beschreibe die Stunde in 3 Wörtern" (ideal für die Wortwolke). Titel und Frage lassen sich jederzeit ändern und mit „Speichern" übernehmen.</li>
        <li><strong>KI-Auswertung anfordern</strong> markiert die Lektion – dein Auswertungs-Server holt sie ab (i.d.R. wenige Minuten) und legt den Bericht in deinem Ordner ab. Die Markierung verschwindet, sobald sie erledigt ist.</li>
        <li>Beim Löschen bleiben die Antworten erhalten – sie verlieren nur die Lektionszuordnung.</li>
      </ul>
    </div>
  </div></div></div>

  <footer>© 2025 Daniel Rutz. Alle Rechte vorbehalten.</footer>

  <script>
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    function post(data){ return $.ajax({url:'lesson_manage.php', type:'POST', dataType:'json', headers:{'X-CSRF-TOKEN':csrf}, data:data}); }

    $('#addForm').on('submit', function(e){ e.preventDefault();
      post({ action:'add', title:$('#addTitle').val(), question:$('#addQuestion').val() })
        .done(function(r){ if(r.success){ location.reload(); } else { alert(r.message); } })
        .fail(function(){ alert('Fehler.'); });
    });
    $('.act-save').on('click', function(){
      var tr=$(this).closest('tr');
      post({ action:'edit', id:tr.data('id'), title:tr.find('.edit-title').val(), question:tr.find('.edit-question').val() })
        .done(function(r){ if(r.success){ location.reload(); } else { alert(r.message); } });
    });
    $('.act-del').on('click', function(){
      var tr=$(this).closest('tr');
      if(!confirm('Diese Lektion löschen? (Antworten bleiben erhalten)')) return;
      post({ action:'delete', id:tr.data('id') })
        .done(function(r){ if(r.success){ location.reload(); } else { alert(r.message); } });
    });
    $('.act-req').on('click', function(){
      var tr=$(this).closest('tr');
      post({ action:'request', id:tr.data('id') })
        .done(function(r){ if(r.success){ location.reload(); } else { alert(r.message); } });
    });
    $('.copy-link').on('click', function(){
      var l=$(this).data('link');
      navigator.clipboard.writeText(l).then(function(){}, function(){ prompt('Link kopieren:', l); });
      var b=$(this); b.text('kopiert!'); setTimeout(function(){ b.text('kopieren'); }, 1500);
    });
  </script>
</body>
</html>
