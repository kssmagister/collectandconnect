<?php
// Serverseitige Login-Wache: nicht angemeldete Besucher sehen die Seite gar nicht.
require_once __DIR__ . '/db.php';
if (empty($_SESSION['loggedin'])) { header('Location: login.html'); exit; }
$csrfToken   = csrf_token();
$teacherName = $_SESSION['teacher_name'] ?? '';
$teacherCode = $_SESSION['teacher_code'] ?? '';
$isAdmin     = !empty($_SESSION['is_admin']);
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
      . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$shareLink = $base . '/?t=' . rawurlencode($teacherCode);

// Persoenliche Klassenauswahl (leer = alle Klassen)
$conn = db();
$myClasses = teacher_classes($conn, current_teacher_id());
$myLessons = [];
$ls = $conn->prepare('SELECT l.id, l.title, COUNT(s.id) AS n
                      FROM lessons l LEFT JOIN submissions s ON s.lesson_id = l.id
                      WHERE l.teacher_id = ? GROUP BY l.id, l.title ORDER BY l.created_at DESC');
$tid = current_teacher_id();
$ls->bind_param('i', $tid);
$ls->execute();
$lr = $ls->get_result();
while ($row = $lr->fetch_assoc()) { $myLessons[] = $row; }
$ls->close();
$conn->close();
$classGroups = all_classes();
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES); ?>">
  <title>Admin – KSS COLLECT &amp; CONNECT</title>
  <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>
  <style>
    .container { max-width: 1100px; }
    .entry-card { background:#fff; border-radius:6px; padding:1rem; margin-bottom:0.75rem;
      box-shadow:0 2px 6px rgba(0,0,0,0.06); }
    .entry-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;
      border-bottom:1px solid #dee2e6; padding-bottom:0.5rem; margin-bottom:0.75rem; }
    .badge-type { background:#495057; color:#fff; padding:0.25rem 0.6rem; border-radius:12px; font-size:12px; font-weight:600; }
    .badge-class { background:#6c757d; color:#fff; padding:0.2rem 0.5rem; border-radius:10px; font-size:12px; margin-left:0.4rem; }
    .badge-nick { background:#e9ecef; color:#495057; padding:0.2rem 0.5rem; border-radius:10px; font-size:12px; margin-left:0.4rem; }
    .badge-lesson { background:#17a2b8; color:#fff; padding:0.2rem 0.5rem; border-radius:10px; font-size:12px; margin-left:0.4rem; }
    .badge-scale { display:inline-block; min-width:1.6rem; text-align:center; border-radius:6px; padding:0.1rem 0.5rem; font-weight:700; color:#fff; }
    .ts { color:#999; font-size:11px; }
    .field-label { font-weight:600; color:#495057; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.15rem; }
    .field-content { background:#fafafa; padding:0.5rem; border-radius:4px; border-left:2px solid #dee2e6;
      white-space:pre-wrap; word-wrap:break-word; font-size:13px; color:#333; margin-bottom:0.5rem; }
    .stat-number { font-size:1.5rem; font-weight:bold; color:#495057; }
    .stat-label { color:#999; font-size:11px; text-transform:uppercase; letter-spacing:0.5px; }
    .stat-item { text-align:center; padding:0.5rem; }
  </style>
</head>
<body>
  <div class="container">
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
      <h1 class="mb-0">Admin – Antworten</h1>
      <div>
        <button class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#helpModal">❓ Hilfe</button>
        <a href="lessons.php" class="btn btn-info btn-sm">Lektionen</a>
        <a href="classes.php" class="btn btn-outline-info btn-sm">Meine Klassen</a>
        <?php if ($isAdmin): ?><a href="teachers.php" class="btn btn-dark btn-sm">Konten verwalten</a><?php endif; ?>
        <button id="beamerBtn" class="btn btn-secondary btn-sm">🖥 Beamer (Feedback)</button>
        <button id="exportCSV" class="btn btn-success btn-sm">CSV</button>
        <button id="exportJSON" class="btn btn-info btn-sm">JSON</button>
        <button id="clearBtn" class="btn btn-warning btn-sm">Löschen</button>
        <button id="logoutBtn" class="btn btn-danger btn-sm">Logout</button>
      </div>
    </div>

    <!-- Angemeldete Person + persoenlicher Link zum Teilen mit Schuelern -->
    <div class="entry-card" style="padding:0.6rem 1rem;">
      <div class="d-flex align-items-center flex-wrap" style="gap:0.5rem; font-size:13px;">
        <span>Angemeldet als <strong><?php echo htmlspecialchars($teacherName, ENT_QUOTES); ?></strong></span>
        <span class="text-muted">·</span>
        <span>Dein Link für Schüler:</span>
        <code id="shareLink" style="font-size:12px;"><?php echo htmlspecialchars($shareLink, ENT_QUOTES); ?></code>
        <button id="copyLink" class="btn btn-outline-secondary btn-sm py-0">kopieren</button>
      </div>
    </div>

    <!-- Statistiken -->
    <div class="entry-card">
      <div class="row">
        <div class="col"><div class="stat-item"><div class="stat-number" id="statTotal">0</div><div class="stat-label">Gesamt</div></div></div>
        <div class="col"><div class="stat-item"><div class="stat-number" id="statClasses">0</div><div class="stat-label">Klassen</div></div></div>
        <div class="col"><div class="stat-item"><div class="stat-number" id="statScale">–</div><div class="stat-label">Ø Sicherheit</div></div></div>
      </div>
      <div class="text-center text-muted" id="typeCounts" style="font-size:12px;"></div>
    </div>

    <!-- Filter -->
    <div class="entry-card">
      <form id="filterForm">
        <div class="row">
          <div class="col-md-3">
            <label>Typ</label>
            <select class="form-control form-control-sm" id="filterType">
              <option value="">Alle Typen</option>
              <option value="feedback">Feedback</option>
              <option value="exit_ticket">Exit-Ticket</option>
              <option value="strukturiert">Strukturiert</option>
            </select>
          </div>
          <div class="col-md-3">
            <label>Klasse</label>
            <select class="form-control form-control-sm" id="filterClass">
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
          </div>
          <div class="col-md-3">
            <label>Nickname</label>
            <input type="text" class="form-control form-control-sm" id="filterNick" placeholder="Suchen...">
          </div>
          <div class="col-md-3">
            <label>Sortierung</label>
            <select class="form-control form-control-sm" id="filterSort">
              <option value="desc">Neueste zuerst</option>
              <option value="asc">Älteste zuerst</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm btn-block mt-2">Filter anwenden</button>
      </form>
    </div>

    <!-- Lektions-Export -->
    <div class="entry-card">
      <div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
        <strong style="font-size:14px;">Lektionen exportieren</strong>
        <span>
          <button type="button" class="btn btn-outline-secondary btn-sm py-0" id="lessonAll">alle</button>
          <button type="button" class="btn btn-outline-secondary btn-sm py-0" id="lessonNone">keine</button>
        </span>
      </div>
      <div id="lessonList" style="max-height:180px; overflow:auto; font-size:13px;">
        <?php foreach ($myLessons as $l): ?>
          <div class="form-check">
            <input class="form-check-input lesson-cb" type="checkbox" value="<?php echo (int)$l['id']; ?>" id="les<?php echo (int)$l['id']; ?>">
            <label class="form-check-label" for="les<?php echo (int)$l['id']; ?>"><?php echo htmlspecialchars((string)$l['title'], ENT_QUOTES); ?> <span class="text-muted">(<?php echo (int)$l['n']; ?>)</span></label>
          </div>
        <?php endforeach; ?>
        <div class="form-check">
          <input class="form-check-input lesson-cb" type="checkbox" value="0" id="les0">
          <label class="form-check-label" for="les0"><em>Ohne Lektion</em></label>
        </div>
      </div>
      <div class="mt-2">
        <button type="button" class="btn btn-success btn-sm lesson-export" data-fmt="csv">CSV</button>
        <button type="button" class="btn btn-info btn-sm lesson-export" data-fmt="json">JSON</button>
        <button type="button" class="btn btn-secondary btn-sm lesson-export" data-fmt="md">Markdown</button>
        <span class="text-muted ml-2" style="font-size:12px;">Die Filter oben gelten hier nicht.</span>
      </div>
    </div>

    <div id="entries"><p class="text-center text-muted">Lädt…</p></div>
  </div>

  <footer>© 2025 Daniel Rutz. Alle Rechte vorbehalten.</footer>

  <div class="modal fade" id="helpModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">So funktioniert der Admin-Bereich</h5>
      <button type="button" class="close" data-dismiss="modal">&times;</button></div>
    <div class="modal-body" style="font-size:14px;">
      <ul class="pl-3 mb-0">
        <li>Du siehst <strong>nur deine eigenen</strong> Antworten.</li>
        <li><strong>Dein Link für Schüler</strong> steht oben — teile ihn (z.B. per QR-Code/Beamer). Nur darüber landen Antworten bei dir.</li>
        <li><strong>Filter</strong> nach Typ/Klasse/Nickname; die Statistik zeigt u.a. die Ø-Sicherheit der Exit-Tickets.</li>
        <li><strong>🖥 Beamer (Feedback)</strong> öffnet die Präsentationsansicht (Karten oder Wortwolke, Vollbild).</li>
        <li><strong>CSV/JSON</strong> exportiert die aktuelle Auswahl; <strong>Löschen</strong> entfernt deine Einträge (nur den gewählten Typ, falls gefiltert).</li>
        <?php if ($isAdmin): ?><li><strong>Konten verwalten</strong> (nur Admin): weitere Lehrpersonen anlegen — jede mit eigenem Login und eigenem Link.</li><?php endif; ?>
      </ul>
    </div>
  </div></div></div>

  <script>
    const TYPE_LABELS = { feedback:'Feedback', exit_ticket:'Exit-Ticket', strukturiert:'Strukturiert' };
    const FIELD_LABELS = {
      text:'Feedback', erkenntnis:'Wichtigste Erkenntnis', frage:'Offene Frage',
      sicherheit:'Sicherheit', hilfe:'Was würde helfen',
      was:'Was', wann:'Wann', warum:'Warum', folgen:'Folgen', beispiel:'Beispiel/Quelle'
    };
    const SCALE_TEXT = {1:'sehr unsicher',2:'eher unsicher',3:'mittel',4:'ziemlich sicher',5:'sehr sicher'};
    const SCALE_COLOR = {1:'#dc3545',2:'#fd7e14',3:'#ffc107',4:'#28a745',5:'#198754'};
    let allEntries = [];
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    $('#logoutBtn').on('click', function(){ $.post('logout.php', function(){ window.location.href='login.html'; }); });

    // Beamer-Ansicht (Feedback) im neuen Tab, mit aktuell gewaehltem Klassenfilter
    $('#beamerBtn').on('click', function(){
      var k = $('#filterClass').val();
      window.open('feedback_view.php' + (k ? ('?klasse=' + encodeURIComponent(k)) : ''), '_blank');
    });

    // Persoenlichen Link kopieren
    $('#copyLink').on('click', function(){
      var l = $('#shareLink').text();
      navigator.clipboard.writeText(l).then(function(){}, function(){ prompt('Link kopieren:', l); });
      var b = $(this); b.text('kopiert!'); setTimeout(function(){ b.text('kopieren'); }, 1500);
    });

    function esc(t){
      if(t===null||t===undefined) return '';
      return String(t).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
    }
    function fmtDate(t){ return new Date(t.replace(' ','T')).toLocaleString('de-DE',
      {day:'2-digit',month:'2-digit',year:'2-digit',hour:'2-digit',minute:'2-digit'}); }

    function load(){
      const p = {};
      if($('#filterType').val()) p.form_type=$('#filterType').val();
      if($('#filterClass').val()) p.klasse=$('#filterClass').val();
      if($('#filterNick').val()) p.nickname=$('#filterNick').val();
      p.sort=$('#filterSort').val();
      $.getJSON('getSubmissions.php', p)
        .done(function(resp){
          if(resp.success){ allEntries=resp.data; render(allEntries); stats(allEntries); }
          else { $('#entries').html('<div class="alert alert-danger">'+esc(resp.message)+'</div>'); }
        })
        .fail(function(xhr){
          if(xhr.status===401){ window.location.href='login.html'; return; }
          $('#entries').html('<div class="alert alert-danger">Fehler beim Laden.</div>');
        });
    }

    function render(entries){
      const c = $('#entries').empty();
      if(!entries.length){ c.html('<div class="text-center text-muted py-4">Keine Einträge gefunden.</div>'); return; }
      entries.forEach(function(e){
        const card = $('<div class="entry-card">');
        let head = '<div class="entry-header"><div><span class="badge-type">'+esc(TYPE_LABELS[e.form_type]||e.form_type)+'</span>'
          + '<span class="badge-class">'+esc(e.klasse)+'</span>'
          + (e.lesson_title ? '<span class="badge-lesson">'+esc(e.lesson_title)+'</span>' : '')
          + (e.nickname ? '<span class="badge-nick">'+esc(e.nickname)+'</span>' : '')
          + '</div><span class="ts">'+fmtDate(e.created_at)+'</span></div>';
        card.append(head);
        const pl = e.payload || {};
        Object.keys(FIELD_LABELS).forEach(function(key){
          if(pl[key]===undefined || pl[key]==='') return;
          card.append('<div class="field-label">'+esc(FIELD_LABELS[key])+'</div>');
          if(key==='sicherheit'){
            const n=parseInt(pl[key],10);
            card.append('<div class="field-content"><span class="badge-scale" style="background:'+(SCALE_COLOR[n]||'#6c757d')+'">'+n+'</span> '+esc(SCALE_TEXT[n]||'')+'</div>');
          } else {
            card.append('<div class="field-content">'+esc(pl[key])+'</div>');
          }
        });
        c.append(card);
      });
    }

    function stats(entries){
      $('#statTotal').text(entries.length);
      $('#statClasses').text(new Set(entries.map(e=>e.klasse)).size);
      const scales = entries.filter(e=>e.payload && e.payload.sicherheit).map(e=>parseInt(e.payload.sicherheit,10));
      $('#statScale').text(scales.length ? (scales.reduce((a,b)=>a+b,0)/scales.length).toFixed(1) : '–');
      const counts = {};
      entries.forEach(e=>{ counts[e.form_type]=(counts[e.form_type]||0)+1; });
      $('#typeCounts').text(Object.keys(counts).map(k=>(TYPE_LABELS[k]||k)+': '+counts[k]).join('  ·  '));
    }

    $('#filterForm').on('submit', function(e){ e.preventDefault(); load(); });

    // Export CSV (ein Inhalts-Feld je Zeile, alle Antwortteile zusammengefasst)
    $('#exportCSV').on('click', function(){
      if(!allEntries.length){ alert('Keine Daten.'); return; }
      let csv='ID;Typ;Klasse;Nickname;Zeit;Inhalt\n';
      allEntries.forEach(function(e){
        const inhalt = Object.keys(e.payload||{}).map(k=>(FIELD_LABELS[k]||k)+': '+e.payload[k]).join(' | ');
        const q = s => '"'+String(s==null?'':s).replace(/"/g,'""')+'"';
        csv += [e.id, q(TYPE_LABELS[e.form_type]||e.form_type), q(e.klasse), q(e.nickname||''), q(e.created_at), q(inhalt)].join(';')+'\n';
      });
      dl(new Blob(['﻿'+csv],{type:'text/csv;charset=utf-8;'}), 'antworten_'+today()+'.csv');
    });
    // Export JSON (Rohdaten inkl. payload-Objekt)
    $('#exportJSON').on('click', function(){
      if(!allEntries.length){ alert('Keine Daten.'); return; }
      dl(new Blob([JSON.stringify(allEntries,null,2)],{type:'application/json'}), 'antworten_'+today()+'.json');
    });
    // Export einzelner Lektionen (CSV / JSON / Markdown), unabhaengig von den Filtern oben
    $('#lessonAll').on('click', function(){ $('.lesson-cb').prop('checked', true); });
    $('#lessonNone').on('click', function(){ $('.lesson-cb').prop('checked', false); });
    $('.lesson-export').on('click', function(){
      const fmt = $(this).data('fmt');
      const ids = $('.lesson-cb:checked').map(function(){ return this.value; }).get();
      if(!ids.length){ alert('Bitte mindestens eine Lektion auswählen.'); return; }
      $.getJSON('getSubmissions.php', { lessons: ids.join(','), sort: 'asc' })
        .done(function(resp){
          if(!resp.success || !resp.data.length){ alert('Keine Daten in der Auswahl.'); return; }
          const d = resp.data, name = 'lektionen_'+today();
          if(fmt==='json'){
            dl(new Blob([JSON.stringify(d,null,2)],{type:'application/json'}), name+'.json');
          } else if(fmt==='csv'){
            const q = s => '"'+String(s==null?'':s).replace(/"/g,'""')+'"';
            let csv='ID;Lektion;Typ;Klasse;Nickname;Zeit;Inhalt\n';
            d.forEach(function(e){
              const inhalt = Object.keys(e.payload||{}).map(k=>(FIELD_LABELS[k]||k)+': '+e.payload[k]).join(' | ');
              csv += [e.id, q(e.lesson_title||''), q(TYPE_LABELS[e.form_type]||e.form_type), q(e.klasse), q(e.nickname||''), q(e.created_at), q(inhalt)].join(';')+'\n';
            });
            dl(new Blob(['﻿'+csv],{type:'text/csv;charset=utf-8;'}), name+'.csv');
          } else {
            const groups = {}, order = [];
            d.forEach(function(e){
              const t = e.lesson_title || 'Ohne Lektion';
              if(!groups[t]){ groups[t]=[]; order.push(t); }
              groups[t].push(e);
            });
            let md = '# Antworten nach Lektion\n\n';
            order.forEach(function(t){
              md += '## '+t+' ('+groups[t].length+')\n\n';
              groups[t].forEach(function(e){
                md += '### '+(TYPE_LABELS[e.form_type]||e.form_type)+' · '+e.klasse+(e.nickname?' · '+e.nickname:'')+' · '+e.created_at+'\n\n';
                Object.keys(e.payload||{}).forEach(function(k){
                  const v = String(e.payload[k]).replace(/\n/g,'\n  ');
                  md += '- **'+(FIELD_LABELS[k]||k)+':** '+v+'\n';
                });
                md += '\n';
              });
            });
            dl(new Blob([md],{type:'text/markdown;charset=utf-8;'}), name+'.md');
          }
        })
        .fail(function(xhr){ if(xhr.status===401){ window.location.href='login.html'; return; } alert('Fehler beim Laden.'); });
    });
    function today(){ return new Date().toISOString().slice(0,10); }
    function dl(blob,name){ const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download=name; a.click(); }

    // Löschen – respektiert den aktuell gewählten Typ-Filter
    $('#clearBtn').on('click', function(){
      const type=$('#filterType').val();
      const scope = type ? 'nur „'+(TYPE_LABELS[type]||type)+'"' : 'ALLE Typen';
      if(!confirm('Wirklich '+scope+' unwiderruflich löschen?')) return;
      $.ajax({
        url: 'clearSubmissions.php', type: 'POST', dataType: 'json',
        headers: { 'X-CSRF-TOKEN': csrfToken },
        data: type ? { form_type: type } : {}
      })
        .done(function(r){ if(r.success){ alert('Gelöscht.'); load(); } else { alert('Fehler: '+r.message); } })
        .fail(function(xhr){ alert('Fehler beim Löschen' + (xhr.status===403 ? ' (CSRF/Session abgelaufen – bitte neu anmelden).' : '.')); });
    });

    load();
  </script>
</body>
</html>
