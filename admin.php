<?php
// Serverseitige Login-Wache: nicht angemeldete Besucher sehen die Seite gar nicht.
require_once __DIR__ . '/db.php';
if (empty($_SESSION['loggedin'])) { header('Location: login.html'); exit; }
$csrfToken = csrf_token();
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
  <style>
    .container { max-width: 1100px; }
    .entry-card { background:#fff; border-radius:6px; padding:1rem; margin-bottom:0.75rem;
      box-shadow:0 2px 6px rgba(0,0,0,0.06); }
    .entry-header { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap;
      border-bottom:1px solid #dee2e6; padding-bottom:0.5rem; margin-bottom:0.75rem; }
    .badge-type { background:#495057; color:#fff; padding:0.25rem 0.6rem; border-radius:12px; font-size:12px; font-weight:600; }
    .badge-class { background:#6c757d; color:#fff; padding:0.2rem 0.5rem; border-radius:10px; font-size:12px; margin-left:0.4rem; }
    .badge-nick { background:#e9ecef; color:#495057; padding:0.2rem 0.5rem; border-radius:10px; font-size:12px; margin-left:0.4rem; }
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
    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
      <h1 class="mb-0">Admin – Antworten</h1>
      <div>
        <button id="exportCSV" class="btn btn-success btn-sm">CSV</button>
        <button id="exportJSON" class="btn btn-info btn-sm">JSON</button>
        <button id="clearBtn" class="btn btn-warning btn-sm">Löschen</button>
        <button id="logoutBtn" class="btn btn-danger btn-sm">Logout</button>
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
              <optgroup label="FMS"><option>1FMS</option><option>2FMS</option><option>3FMS</option></optgroup>
              <optgroup label="GYM-G"><option>1GYM-G</option><option>2GYM-G</option><option>3GYM-G</option><option>4GYM-G</option></optgroup>
              <optgroup label="WMS/IMS"><option>1WMS/IMS</option><option>2WMS/IMS</option><option>3WMS/IMS</option></optgroup>
              <optgroup label="Sonstige"><option>LatInt</option><option>efG</option><option>ffGR</option><option>EXTRA</option></optgroup>
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

    <div id="entries"><p class="text-center text-muted">Lädt…</p></div>
  </div>

  <footer>© 2025 Daniel Rutz. Alle Rechte vorbehalten.</footer>

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
