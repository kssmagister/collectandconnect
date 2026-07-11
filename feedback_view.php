<?php
// Beamer-/Praesentationsansicht fuer Feedback-Eintraege.
// Login-geschuetzt (nur die Lehrperson zeigt das im Klassenzimmer).
require_once __DIR__ . '/db.php';
if (empty($_SESSION['loggedin'])) { header('Location: login.html'); exit; }
$initialKlasse = isset($_GET['klasse']) ? (string) $_GET['klasse'] : '';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Feedback – Beamer-Ansicht</title>
  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <style>
    body { background:#f5f5f5; margin:0; }
    .topbar {
      position:sticky; top:0; z-index:10; background:#495057; color:#fff;
      padding:0.6rem 1rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap;
    }
    .topbar h1 { font-size:1.25rem; font-weight:600; margin:0; }
    .topbar .spacer { flex:1 1 auto; }
    .topbar select { max-width:220px; }
    .topbar .meta { font-size:0.8rem; opacity:0.8; }
    .grid {
      display:grid; grid-template-columns:repeat(auto-fill, minmax(340px, 1fr));
      gap:1rem; padding:1.25rem;
    }
    .fb-card {
      background:#fff; border-radius:10px; padding:1.25rem 1.4rem;
      box-shadow:0 2px 8px rgba(0,0,0,0.10); border-left:5px solid #495057;
      display:flex; flex-direction:column;
    }
    .fb-text {
      font-size:1.35rem; line-height:1.5; color:#212529;
      white-space:pre-wrap; word-wrap:break-word; flex:1 1 auto;
    }
    .fb-foot {
      margin-top:0.9rem; padding-top:0.6rem; border-top:1px solid #e9ecef;
      display:flex; justify-content:space-between; align-items:center; gap:0.5rem;
      font-size:0.85rem; color:#6c757d; flex-wrap:wrap;
    }
    .fb-badges .badge-class {
      background:#6c757d; color:#fff; padding:0.2rem 0.55rem; border-radius:10px;
      font-size:0.8rem; font-weight:600;
    }
    .fb-badges .badge-nick {
      background:#e9ecef; color:#495057; padding:0.2rem 0.55rem; border-radius:10px;
      font-size:0.8rem; margin-left:0.35rem;
    }
    .empty { text-align:center; color:#6c757d; padding:4rem 1rem; font-size:1.25rem; }
    .btn-bar .btn { font-size:0.85rem; }
    /* Groessere Schrift im Vollbild fuer bessere Lesbarkeit auf Distanz */
    :fullscreen .fb-text { font-size:1.6rem; }
    :fullscreen .grid { grid-template-columns:repeat(auto-fill, minmax(380px, 1fr)); }
  </style>
</head>
<body>
  <div class="topbar">
    <h1>💬 Feedback</h1>
    <select id="klasse" class="form-control form-control-sm">
      <option value="">Alle Klassen</option>
      <optgroup label="FMS"><option>1FMS</option><option>2FMS</option><option>3FMS</option></optgroup>
      <optgroup label="GYM-G"><option>1GYM-G</option><option>2GYM-G</option><option>3GYM-G</option><option>4GYM-G</option></optgroup>
      <optgroup label="WMS/IMS"><option>1WMS/IMS</option><option>2WMS/IMS</option><option>3WMS/IMS</option></optgroup>
      <optgroup label="Sonstige"><option>LatInt</option><option>efG</option><option>ffGR</option><option>EXTRA</option></optgroup>
    </select>
    <span class="meta"><span id="count">0</span> Einträge · Stand <span id="stamp">–</span></span>
    <div class="spacer"></div>
    <div class="btn-bar">
      <button id="refreshBtn" class="btn btn-light btn-sm">Aktualisieren</button>
      <button id="fsBtn" class="btn btn-light btn-sm">Vollbild</button>
      <a href="admin.php" class="btn btn-outline-light btn-sm">← Admin</a>
    </div>
  </div>

  <div id="grid" class="grid"></div>
  <div id="empty" class="empty" style="display:none;">Noch kein Feedback für diese Auswahl.</div>

  <script>
    var initialKlasse = <?php echo json_encode($initialKlasse, JSON_UNESCAPED_UNICODE); ?>;

    function esc(t){
      if(t===null||t===undefined) return '';
      return String(t).replace(/[&<>"']/g, function(m){
        return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m];
      });
    }
    function fmtDate(t){
      return new Date(t.replace(' ','T')).toLocaleString('de-DE',
        {day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
    }

    function load(){
      var p = { form_type:'feedback' };
      var k = $('#klasse').val();
      if(k) p.klasse = k;
      $.getJSON('getSubmissions.php', p)
        .done(function(resp){
          if(!resp.success){ return; }
          var entries = resp.data || [];
          var grid = $('#grid').empty();
          $('#count').text(entries.length);
          $('#stamp').text(new Date().toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit',second:'2-digit'}));
          $('#empty').toggle(entries.length === 0);
          entries.forEach(function(e){
            var text = (e.payload && e.payload.text) ? e.payload.text : '';
            var card = $('<div class="fb-card">');
            card.append('<div class="fb-text">' + esc(text) + '</div>');
            var foot = '<div class="fb-foot"><span class="fb-badges"><span class="badge-class">' + esc(e.klasse) + '</span>'
              + (e.nickname ? '<span class="badge-nick">' + esc(e.nickname) + '</span>' : '')
              + '</span><span>' + fmtDate(e.created_at) + '</span></div>';
            card.append(foot);
            grid.append(card);
          });
        })
        .fail(function(xhr){
          if(xhr.status === 401){ window.location.href = 'login.html'; }
        });
    }

    // Vollbild fuer den Beamer
    $('#fsBtn').on('click', function(){
      if(!document.fullscreenElement){ document.documentElement.requestFullscreen(); }
      else { document.exitFullscreen(); }
    });

    $('#refreshBtn').on('click', load);
    $('#klasse').on('change', load);

    // Initialen Klassenfilter aus URL uebernehmen
    if(initialKlasse){ $('#klasse').val(initialKlasse); }

    load();
    setInterval(load, 25000); // alle 25 s automatisch aktualisieren
  </script>
</body>
</html>
