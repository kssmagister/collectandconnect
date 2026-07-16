<?php
// Beamer-/Praesentationsansicht fuer Feedback: Karten ODER Wortwolke.
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
  </style>
</head>
<body>
  <div class="topbar">
    <h1>💬 Feedback</h1>

    <div class="btn-group btn-group-toggle btn-group-sm" role="group">
      <button id="modeCards" class="btn btn-light active">Karten</button>
      <button id="modeCloud" class="btn btn-outline-light">Wortwolke</button>
    </div>

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

  <div id="content">
    <div id="grid"></div>
    <div id="cloudWrap" style="display:none;"><canvas id="cloud"></canvas></div>
    <div id="empty">Noch kein Feedback für diese Auswahl.</div>
  </div>

  <script>
    var initialKlasse = <?php echo json_encode($initialKlasse, JSON_UNESCAPED_UNICODE); ?>;
    var entries = [];
    var mode = 'cards'; // 'cards' | 'cloud'

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
      var p = { form_type:'feedback' };
      var k = $('#klasse').val();
      if(k) p.klasse = k;
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
        var text = (e.payload && e.payload.text) ? e.payload.text : '';
        var card = $('<div class="fb-card">');
        card.append('<div class="fb-text">' + esc(text) + '</div>');
        card.append('<div class="fb-foot"><span class="fb-badges"><span class="badge-class">' + esc(e.klasse) + '</span>'
          + (e.nickname ? '<span class="badge-nick">' + esc(e.nickname) + '</span>' : '')
          + '</span><span>' + fmtDate(e.created_at) + '</span></div>');
        grid.append(card);
      });
    }

    // Woerter aus allen Feedback-Texten zaehlen (ohne Fuellwoerter).
    function wordFrequencies(){
      var counts = {};
      entries.forEach(function(e){
        var text = (e.payload && e.payload.text) ? e.payload.text : '';
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
    load();
    setInterval(load, 25000); // alle 25 s automatisch aktualisieren
  </script>
</body>
</html>
