<?php
// Persoenliche Klassenauswahl der eingeloggten Lehrperson.
require_once __DIR__ . '/db.php';
if (empty($_SESSION['loggedin'])) { header('Location: login.html'); exit; }
$csrfToken = csrf_token();
$me = current_teacher_id();

$conn = db();
$mine = teacher_classes($conn, $me);
$conn->close();

$groups = all_classes();
function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo h($csrfToken); ?>">
  <title>Meine Klassen – KSS COLLECT &amp; CONNECT</title>
  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
  <style>
    .container { max-width: 800px; }
    .class-group { background:#fafafa; border-left:3px solid #6c757d; border-radius:6px;
                   padding:0.8rem 1rem; margin-bottom:0.8rem; }
    .class-group h6 { font-weight:600; color:#495057; margin-bottom:0.5rem; font-size:13px;
                      text-transform:uppercase; letter-spacing:0.5px; }
    .form-check { margin-right:1.2rem; }
    .form-check-label { font-size:14px; }
  </style>
</head>
<body>
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
      <h1 class="mb-0">Meine Klassen</h1>
      <div>
        <button class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#helpModal">❓ Hilfe</button>
        <a href="admin.php" class="btn btn-secondary btn-sm">← Admin</a>
      </div>
    </div>
    <p class="text-muted" style="font-size:13px;">
      Wähle die Klassen, die du unterrichtest. Deine Schüler sehen dann nur noch diese
      in der Klassen-Auswahl. <strong>Ohne Auswahl werden alle Klassen angezeigt.</strong>
    </p>

    <div id="status" class="alert alert-success" style="display:none;"></div>

    <form id="classForm">
      <?php foreach ($groups as $groupName => $klassen): ?>
        <div class="class-group">
          <h6><?php echo h($groupName); ?></h6>
          <div class="d-flex flex-wrap">
            <?php foreach ($klassen as $k): ?>
              <div class="form-check">
                <input class="form-check-input cls" type="checkbox" value="<?php echo h($k); ?>"
                       id="c_<?php echo h(md5($k)); ?>" <?php echo in_array($k, $mine, true) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="c_<?php echo h(md5($k)); ?>"><?php echo h($k); ?></label>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>

      <div class="d-flex align-items-center" style="gap:0.5rem;">
        <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
        <button type="button" id="allBtn" class="btn btn-outline-secondary btn-sm">Alle abwählen (= alle anzeigen)</button>
        <span class="text-muted" style="font-size:12px;" id="hint"></span>
      </div>
    </form>
  </div>

  <div class="modal fade" id="helpModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Meine Klassen</h5>
      <button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body" style="font-size:14px;">
      <ul class="pl-3 mb-0">
        <li>Die Auswahl gilt <strong>nur für dich</strong>. Andere Lehrpersonen haben ihre eigene.</li>
        <li>Schüler, die deinen Link öffnen, sehen im Formular nur noch deine Klassen – das macht die Auswahl deutlich übersichtlicher.</li>
        <li>Auch die Filter im Admin und in der Beamer-Ansicht zeigen dann nur deine Klassen.</li>
        <li><strong>Keine Auswahl = alle Klassen</strong> (Verhalten wie bisher). Bereits gespeicherte Antworten bleiben davon unberührt.</li>
      </ul>
    </div>
  </div></div></div>

  <footer>© 2025 Daniel Rutz. Alle Rechte vorbehalten.</footer>

  <script>
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    function refreshHint(){
      var n = $('.cls:checked').length;
      $('#hint').text(n ? (n + ' Klasse(n) ausgewählt') : 'keine Auswahl → alle Klassen werden angezeigt');
    }
    $('.cls').on('change', refreshHint); refreshHint();

    $('#allBtn').on('click', function(){ $('.cls').prop('checked', false); refreshHint(); });

    $('#classForm').on('submit', function(e){
      e.preventDefault();
      var chosen = $('.cls:checked').map(function(){ return this.value; }).get();
      $.ajax({ url:'class_manage.php', type:'POST', dataType:'json',
               headers:{'X-CSRF-TOKEN':csrf},
               data:{ action:'save', classes: chosen } })
        .done(function(r){
          if(r.success){
            $('#status').text(r.count ? ('Gespeichert: ' + r.count + ' Klasse(n).')
                                      : 'Gespeichert: keine Auswahl – es werden alle Klassen angezeigt.')
                        .show();
            setTimeout(function(){ $('#status').fadeOut(); }, 2500);
          } else { alert(r.message); }
        })
        .fail(function(){ alert('Fehler beim Speichern.'); });
    });
  </script>
</body>
</html>
