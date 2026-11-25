<?php
// Beamer-/Praesentationsansicht fuer Feedback- ODER Aufgaben-Antworten: Karten ODER Wortwolke.
// Login-geschuetzt (nur die Lehrperson zeigt das im Klassenzimmer).
// Parameter (alle optional): ?klasse=..., ?type=feedback|aufgabe, ?lesson=ID
// Mit ?lesson und festem Antworttool der Lektion ergibt sich der Typ automatisch.
require_once __DIR__ . '/db.php';
if (empty($_SESSION['loggedin'])) { header('Location: login.html'); exit; }
$initialKlasse = isset($_GET['klasse']) ? (string) $_GET['klasse'] : '';
$initialLesson = isset($_GET['lesson']) ? (int) $_GET['lesson'] : 0;
$initialType   = (isset($_GET['type']) && $_GET['type'] === 'aufgabe') ? 'aufgabe' : '';

// Persoenliche Klassenauswahl (leer = alle Klassen) + eigene (sichtbare) Lektionen
$conn = db();
$me = current_teacher_id();
$myClasses = teacher_classes($conn, $me);
$lessons = [];
$ls = $conn->prepare("SELECT id, title, form_type, feedback_question FROM lessons
                      WHERE teacher_id = ? AND is_hidden = 0 ORDER BY created_at DESC");
$ls->bind_param('i', $me);
$ls->execute();
$res = $ls->get_result();
while ($row = $res->fetch_assoc()) {
    $lessons[] = ['id' => (int) $row['id'], 'title' => $row['title'],
                  'form_type' => $row['form_type'], 'question' => $row['feedback_question']];
}
$ls->close();
$conn->close();
$classGroups = all_classes();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Beamer-Ansicht – KSS COLLECT &amp; CONNECT</title>
  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/wordcloud2.js/1.2.2/wordcloud2.min.js"></script>
  <style>
    html, body { height:100%; }
    body { background:#f5f5f5; margin:0; display:flex; flex-direction:column; }
    .topbar {
      flex:0 0 auto; background:#495057; color:#fff;
      padding:0.6rem 1rem; display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;
    }
    .topbar h1 { font-size:1.25rem; font-weight:600; margin:0; }
    .topbar .spacer { flex:1 1 auto; }
    .topbar select { max-width:200px; }
    .topbar .meta { font-size:0.8rem; opacity:0.85; }
    .btn-group-toggle .btn { font-size:0.85rem; }
    .btn-bar .btn { font-size:0.85rem; }
    #content { flex:1 1 auto; position:relative; overflow:hidden; }
    #grid {
      position:absolute; inset:0; overflow:auto;
      display:grid; grid-template-columns:1fr;   /* Karten untereinander (bessere Lesbarkeit) */
      gap:1rem; padding:1.25rem; align-content:start;
    }
    #cloudWrap { position:absolute; inset:0; background:#fff; }
    #cloud { width:100%; height:100%; display:block; }
    .fb-card {
      background:#fff; border-radius:10px; padding:1.25rem 1.4rem;
      box-shadow:0 2px 8px rgba(0,0,0,0.10); border-left:5px solid #495057;
      display:flex; flex-direction:column;
      width:100%; max-width:1100px; margin:0 auto;  /* zentriert, nicht zu lange Zeilen */
    }
    .fb-text { font-size:1.35rem; line-height:1.5; color:#212529; white-space:pre-wrap; word-wrap:break-word; flex:1 1 auto; }
    .fb-foot {
      margin-top:0.9rem; padding-top:0.6rem; border-top:1px solid #e9ecef;
      display:flex; justify-content:space-between; align-items:center; gap:0.5rem;
      font-size:0.85rem; color:#6c757d; flex-wrap:wrap;
    }
    .fb-badges .badge-class { background:#6c757d; color:#fff; padding:0.2rem 0.55rem; border-radius:10px; font-size:0.8rem; font-weight:600; }
    .fb-badges .badge-nick { background:#e9ecef; color:#495057; padding:0.2rem 0.55rem; border-radius:10px; font-size:0.8rem; margin-left:0.35rem; }
    #empty {
      position:absolute; inset:0; display:none; align-items:center; justify-content:center;
      color:#6c757d; font-size:1.25rem; text-align:center; padding:2rem; pointer-events:none;
    }
    :fullscreen .fb-text { font-size:1.6rem; }
    #questionBar {
      flex:0 0 auto; background:#e8f4f8; border-bottom:3px solid #17a2b8;
      padding:0.8rem 1.25rem; font-size:1.3rem; line-height:1.45; color:#212529; white-space:pre-wrap;
    }
    #questionBar .q-label { font-weight:700; color:#117a8b; margin-right:0.4rem; }
    :fullscreen #questionBar { font-size:1.55rem; }
    .topbar select.lesson-sel { max-width:260px; }
  </style>
</head>
<body>
  <div class="topbar">
    <h1 id="viewTitle">💬 Feedback</h1>

    <div class="btn-group btn-group-toggle btn-group-sm" role="group">
      <button id="typeFeedback" class="btn btn-light active">💬 Feedback</button>
      <button id="typeAufgabe" class="btn btn-outline-light">✍️ Aufgabe</button>
    </div>

    <select id="lesson" class="form-control form-control-sm lesson-sel">
      <option value="">Alle Lektionen</option>
      <?php foreach ($lessons as $l): ?><option value="<?php echo (int) $l['id']; ?>"><?php echo htmlspecialchars((string) $l['title'], ENT_QUOTES); ?></option><?php endforeach; ?>
    </select>

    <div class="btn-group btn-group-toggle btn-group-sm" role="group">
      <button id="modeCards" class="btn btn-light active">Karten</button>
      <button id="modeCloud" class="btn btn-outline-light">Wortwolke</button>
    </div>

    <select id="klasse" class="form-control form-control-sm">
      <option value="">Alle Klassen</option>
      <?php if ($myClasses): ?>
        <?php foreach ($myClasses as $k): ?><option><?php echo htmlspecialchars($k, ENT_QUOTES); ?></option><?php endforeach; ?>
      <?php else: ?>
        <?php foreach ($classGroups as $g => $ks): ?>
          <optgroup label="<?php echo htmlspecialchars($g, ENT_QUOTES); ?>">
            <?php foreach ($ks as $k): ?><option><?php echo htmlspecialchars($k, ENT_QUOTES); ?></option><?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      <?php endif; ?>
    </select>

    <span class="meta"><span id="count">0</span> Einträge · Stand <span id="stamp">–</span></span>
    <div class="spacer"></div>
    <div class="btn-bar">
      <button id="refreshBtn" class="btn btn-light btn-sm">Aktualisieren</button>
      <button id="fsBtn" class="btn btn-light btn-sm">Vollbild</button>
      <a href="admin.php" class="btn btn-outline-light btn-sm">← Admin</a>
    </div>
  </div>

  <div id="questionBar" style="display:none;"><span class="q-label" id="qLabel">Aufgabe:</span><span id="qText"></span></div>

  <div id="content">
    <div id="grid"></div>
    <div id="cloudWrap" style="display:none;"><canvas id="cloud"></canvas></div>
    <div id="empty">Noch keine Einträge für diese Auswahl.</div>
  </div>

  <script>
    var initialKlasse = <?php echo json_encode($initialKlasse, JSON_UNESCAPED_UNICODE); ?>;
    var initialLesson = <?php echo (int) $initialLesson; ?>;
    var initialType   = <?php echo json_encode($initialType); ?>;
    var LESSONS = <?php echo json_encode($lessons, JSON_UNESCAPED_UNICODE); ?>;
    var entries = [];
    var mode = 'cards';     // 'cards' | 'cloud'
    var type = 'feedback';  // 'feedback' | 'aufgabe' (= submissions.form_type)

    function lessonById(id){ return LESSONS.find(function(l){ return String(l.id) === String(id); }) || null; }
    // Antworttext je Typ (Feedback: payload.text, Aufgabe: payload.antwort)
    function entryText(e){
      var p = e.payload || {};
      return (type === 'aufgabe' ? p.antwort : p.text) || '';
    }

    // Haeufige deutsche Fuellwoerter, die in der Wolke nichts aussagen.
    var STOP = new Set(("und oder aber dass weil denn der die das den dem des ein eine einen einem "
      + "einer eines ich du er sie es wir ihr mir mich dir dich uns euch ihnen sich mein dein sein "
      + "unser euer ist war sind bin bist seid waren haben hat hatte habe hast wird werden wurde "
      + "kann konnte koennte muss musste soll will wollte fuer mit von zu zum zur im in an am auf aus "
      + "bei nach vor ueber unter durch gegen ohne um bis als wie wenn also so noch nur auch schon "
      + "sehr mehr man nicht kein keine ja nein dann hier dort was wer wo warum weil doch mal etwas "
      + "einfach immer wieder ganz gut gute guter gutes viel viele diese dieser dieses jede jeder "
      + "am beim vom zum").split(/\s+/));

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
      var p = { form_type:type };
      var k = $('#klasse').val();
      if(k) p.klasse = k;
      var lid = $('#lesson').val();
      if(lid) p.lessons = lid;
      $.getJSON('getSubmissions.php', p)
        .done(function(resp){
          if(!resp.success) return;
          entries = resp.data || [];
          $('#count').text(entries.length);
          $('#stamp').text(new Date().toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit',second:'2-digit'}));
          render();
        })
        .fail(function(xhr){ if(xhr.status === 401){ window.location.href = 'login.html'; } });
    }

    function render(){
      if(mode === 'cards'){
        $('#cloudWrap').hide(); $('#grid').show();
        renderCards();
      } else {
        $('#grid').hide(); $('#cloudWrap').show();
        renderCloud();
      }
    }

    function renderCards(){
      var grid = $('#grid').empty();
      $('#empty').css('display', entries.length ? 'none' : 'flex');
      entries.forEach(function(e){
        var text = entryText(e);
        var card = $('<div class="fb-card">');
        card.append('<div class="fb-text">' + esc(text) + '</div>');
        card.append('<div class="fb-foot"><span class="fb-badges"><span class="badge-class">' + esc(e.klasse) + '</span>'
          + (e.nickname ? '<span class="badge-nick">' + esc(e.nickname) + '</span>' : '')
          + '</span><span>' + fmtDate(e.created_at) + '</span></div>');
        grid.append(card);
      });
    }

    // Woerter aus allen Antworttexten zaehlen (ohne Fuellwoerter).
    function wordFrequencies(){
      var counts = {};
      entries.forEach(function(e){
        var text = entryText(e);
        text.toLowerCase().split(/[^a-zäöüß]+/).forEach(function(w){
          if(w.length < 3 || STOP.has(w)) return;
          counts[w] = (counts[w] || 0) + 1;
        });
      });
      return Object.keys(counts)
        .map(function(w){ return [w, counts[w]]; })
        .sort(function(a,b){ return b[1] - a[1]; })
        .slice(0, 120);
    }

    function renderCloud(){
      var wrap = document.getElementById('cloudWrap');
      var canvas = document.getElementById('cloud');
      canvas.width  = wrap.clientWidth;
      canvas.height = wrap.clientHeight;

      if(typeof WordCloud === 'undefined'){
        $('#empty').text('Wortwolken-Bibliothek konnte nicht geladen werden (Internet?).').css('display','flex');
        return;
      }
      var list = wordFrequencies();
      if(list.length === 0){
        canvas.getContext('2d').clearRect(0,0,canvas.width,canvas.height);
        $('#empty').text('Noch keine Wörter für diese Auswahl.').css('display','flex');
        return;
      }
      $('#empty').css('display','none');

      var max  = list[0][1];
      var base = Math.min(canvas.width, canvas.height);
      var palette = ['#495057','#17a2b8','#28a745','#e8590c','#6f42c1','#d6336c','#1c7ed6'];

      WordCloud(canvas, {
        list: list,
        gridSize: Math.max(4, Math.round(base / 90)),
        weightFactor: function(count){
          // Groesse ~ Haeufigkeit, gedeckelt, min. lesbar
          return Math.min(base / 5, 14 + Math.sqrt(count / max) * (base / 6));
        },
        fontFamily: 'Helvetica, Arial, sans-serif',
        color: function(){ return palette[Math.floor(Math.random() * palette.length)]; },
        backgroundColor: '#ffffff',
        rotateRatio: 0.25,
        minRotation: -Math.PI/16, maxRotation: Math.PI/16,
        shuffle: false,
        drawOutOfBound: false
      });
    }

    // ── Steuerung ─────────────────────────────────────────────
    function setMode(m){
      mode = m;
      $('#modeCards').toggleClass('btn-light active', m==='cards').toggleClass('btn-outline-light', m!=='cards');
      $('#modeCloud').toggleClass('btn-light active', m==='cloud').toggleClass('btn-outline-light', m!=='cloud');
      render();
    }
    // Typ (Feedback/Aufgabe) umschalten: Titel, Knoepfe, Frage-Leiste, Daten neu laden
    function setType(t, reload){
      type = (t === 'aufgabe') ? 'aufgabe' : 'feedback';
      $('#typeFeedback').toggleClass('btn-light active', type==='feedback').toggleClass('btn-outline-light', type!=='feedback');
      $('#typeAufgabe').toggleClass('btn-light active', type==='aufgabe').toggleClass('btn-outline-light', type!=='aufgabe');
      $('#viewTitle').text(type === 'aufgabe' ? '✍️ Aufgabe' : '💬 Feedback');
      updateQuestion();
      if(reload !== false) load();
    }
    // Frage/Aufgabe der gewaehlten Lektion gross ueber den Karten zeigen
    function updateQuestion(){
      var l = lessonById($('#lesson').val());
      var q = l && l.question ? l.question : '';
      $('#qLabel').text(type === 'aufgabe' ? 'Aufgabe:' : 'Frage:');
      $('#qText').text(q);
      $('#questionBar').toggle(!!q);
      if(mode === 'cloud'){ onResize(); }
    }
    $('#typeFeedback').on('click', function(){ setType('feedback'); });
    $('#typeAufgabe').on('click', function(){ setType('aufgabe'); });
    $('#lesson').on('change', function(){
      // Lektion mit festem Antworttool Feedback/Aufgabe -> Typ automatisch passend
      var l = lessonById(this.value);
      if(l && (l.form_type === 'feedback' || l.form_type === 'aufgabe') && l.form_type !== type){
        setType(l.form_type);
      } else {
        updateQuestion(); load();
      }
    });

    $('#modeCards').on('click', function(){ setMode('cards'); });
    $('#modeCloud').on('click', function(){ setMode('cloud'); });

    $('#refreshBtn').on('click', load);
    $('#klasse').on('change', load);

    $('#fsBtn').on('click', function(){
      if(!document.fullscreenElement){ document.documentElement.requestFullscreen(); }
      else { document.exitFullscreen(); }
    });

    // Bei Groessenaenderung (auch Vollbild) die Wolke neu zeichnen
    var rt;
    function onResize(){ if(mode==='cloud'){ clearTimeout(rt); rt=setTimeout(renderCloud, 200); } }
    window.addEventListener('resize', onResize);
    document.addEventListener('fullscreenchange', onResize);

    if(initialKlasse){ $('#klasse').val(initialKlasse); }
    var startType = initialType;
    if(initialLesson && lessonById(initialLesson)){
      $('#lesson').val(String(initialLesson));
      var il = lessonById(initialLesson);
      if(!startType && il.form_type === 'aufgabe'){ startType = 'aufgabe'; }
    }
    setType(startType || 'feedback', false);
    load();
    setInterval(load, 25000); // alle 25 s automatisch aktualisieren
  </script>
</body>
</html>
