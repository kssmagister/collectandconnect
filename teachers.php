<?php
// Konten-Verwaltung (nur Admin).
require_once __DIR__ . '/db.php';
if (empty($_SESSION['loggedin'])) { header('Location: login.html'); exit; }
if (empty($_SESSION['is_admin'])) { header('Location: admin.php'); exit; }
$csrfToken = csrf_token();

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
      . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

$conn = db();
$teachers = [];
$res = $conn->query("SELECT id, username, name, code, is_admin, created_at FROM teachers ORDER BY is_admin DESC, name ASC");
while ($row = $res->fetch_assoc()) { $teachers[] = $row; }
$conn->close();

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo h($csrfToken); ?>">
  <title>Konten verwalten – KSS COLLECT &amp; CONNECT</title>
  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
  <style>
    .container { max-width:1000px; }
    .table td, .table th { vertical-align:middle; font-size:13px; }
    .link-box { font-size:12px; color:#495057; word-break:break-all; }
    .code-chip { background:#495057; color:#fff; padding:0.15rem 0.5rem; border-radius:10px; font-weight:600; font-size:12px; }
    .inline-input { max-width:150px; display:inline-block; }
  </style>
</head>
<body>
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
      <h1 class="mb-0">Konten verwalten</h1>
      <div>
        <button class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#helpModal">❓ Hilfe</button>
        <a href="admin.php" class="btn btn-secondary btn-sm">← Admin</a>
      </div>
    </div>

    <!-- Neues Konto -->
    <div class="form-section">
      <h5>Neue Lehrperson anlegen</h5>
      <form id="addForm" class="form-row align-items-end">
        <div class="col-md-3 mb-2"><label>Anzeigename</label><input class="form-control form-control-sm" id="addName" placeholder="z.B. Fr. Müller" required></div>
        <div class="col-md-3 mb-2"><label>Benutzername (Login)</label><input class="form-control form-control-sm" id="addUser" placeholder="mueller" required></div>
        <div class="col-md-2 mb-2"><label>Passwort</label><input class="form-control form-control-sm" id="addPass" placeholder="min. 6 Zeichen" required></div>
        <div class="col-md-2 mb-2"><label>Code <span class="text-muted">(optional)</span></label><input class="form-control form-control-sm" id="addCode" placeholder="automatisch"></div>
        <div class="col-md-2 mb-2"><button class="btn btn-primary btn-sm btn-block" type="submit">Anlegen</button></div>
      </form>
    </div>

    <!-- Liste -->
    <div class="table-responsive">
      <table class="table table-striped">
        <thead><tr><th>Name</th><th>Login</th><th>Code</th><th>Persönlicher Link</th><th style="width:1%">Aktionen</th></tr></thead>
        <tbody>
        <?php foreach ($teachers as $t): $link = $base . '/?t=' . rawurlencode($t['code']); ?>
          <tr data-id="<?php echo (int) $t['id']; ?>">
            <td>
              <input class="form-control form-control-sm inline-input edit-name" value="<?php echo h($t['name']); ?>">
              <?php if ($t['is_admin']): ?><span class="badge badge-dark">Admin</span><?php endif; ?>
            </td>
            <td><?php echo h($t['username']); ?></td>
            <td><input class="form-control form-control-sm edit-code" style="max-width:90px" value="<?php echo h($t['code']); ?>"></td>
            <td>
              <span class="link-box"><?php echo h($link); ?></span>
              <button class="btn btn-link btn-sm p-0 copy-link" data-link="<?php echo h($link); ?>">kopieren</button>
            </td>
            <td class="text-nowrap">
              <button class="btn btn-outline-primary btn-sm act-save">Speichern</button>
              <button class="btn btn-outline-secondary btn-sm act-pw">Passwort</button>
              <?php if (!$t['is_admin']): ?>
                <button class="btn btn-outline-danger btn-sm act-del">Löschen</button>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Hilfe -->
  <div class="modal fade" id="helpModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">So funktioniert die Konten-Verwaltung</h5>
      <button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body" style="font-size:14px;">
      <ul class="pl-3 mb-0">
        <li>Jede Lehrperson bekommt ein eigenes Konto und sieht nur ihre eigenen Antworten.</li>
        <li><strong>Persönlicher Link:</strong> Gib deinen Schülern den Link deiner Zeile (<code>?t=CODE</code>). Nur darüber landen Antworten bei dir.</li>
        <li><strong>Code</strong> lässt sich ändern (dann ändern sich die Links); <strong>Passwort</strong> setzt du per Button neu.</li>
        <li>Beim <strong>Löschen</strong> einer Lehrperson werden auch deren Antworten entfernt.</li>
      </ul>
    </div>
  </div></div></div>

  <footer>© 2025 Daniel Rutz. Alle Rechte vorbehalten.</footer>

  <script>
    var csrf = document.querySelector('meta[name="csrf-token"]').content;
    function post(data){ return $.ajax({url:'teacher_manage.php', type:'POST', dataType:'json', headers:{'X-CSRF-TOKEN':csrf}, data:data}); }

    $('#addForm').on('submit', function(e){ e.preventDefault();
      post({ action:'add', name:$('#addName').val(), username:$('#addUser').val(),
             password:$('#addPass').val(), code:$('#addCode').val() })
        .done(function(r){ if(r.success){ location.reload(); } else { alert(r.message); } })
        .fail(function(){ alert('Fehler.'); });
    });

    $('.act-save').on('click', function(){
      var tr=$(this).closest('tr');
      post({ action:'edit', id:tr.data('id'), name:tr.find('.edit-name').val(), code:tr.find('.edit-code').val().toUpperCase() })
        .done(function(r){ if(r.success){ location.reload(); } else { alert(r.message); } });
    });

    $('.act-pw').on('click', function(){
      var tr=$(this).closest('tr');
      var pw=prompt('Neues Passwort (min. 6 Zeichen):'); if(!pw) return;
      post({ action:'resetpw', id:tr.data('id'), password:pw })
        .done(function(r){ alert(r.success?'Passwort gesetzt.':r.message); });
    });

    $('.act-del').on('click', function(){
      var tr=$(this).closest('tr');
      if(!confirm('Diese Lehrperson und ALLE ihre Antworten löschen?')) return;
      post({ action:'delete', id:tr.data('id') })
        .done(function(r){ if(r.success){ location.reload(); } else { alert(r.message); } });
    });

    $('.copy-link').on('click', function(){
      var l=$(this).data('link');
      navigator.clipboard.writeText(l).then(function(){ }, function(){ prompt('Link kopieren:', l); });
      var b=$(this); b.text('kopiert!'); setTimeout(function(){ b.text('kopieren'); }, 1500);
    });
  </script>
</body>
</html>
