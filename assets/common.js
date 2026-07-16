// Gemeinsame Logik der Schueler-Seiten:
//  - liest Lehrer-Code (?t=) und optionalen Lektions-Code (?l=) aus der URL
//  - haengt beide an Links mit [data-carry-t] an (Landing -> Formulare)
//  - zeigt einen Banner mit Lehrername (+ Lektionstitel, falls vorhanden)
(function () {
  var params = new URLSearchParams(location.search);
  window.TEACHER_CODE = params.get('t') || '';
  window.LESSON_CODE = params.get('l') || '';

  // Befuellt das Klassen-Auswahlfeld aus den Serverdaten (teacher_info.php).
  //   d.classes     = persoenliche Auswahl der Lehrperson (hat Vorrang) oder null
  //   d.all_classes = Stammliste { Gruppe: [Klassen] } aus db.php
  function fillClasses(d) {
    var sel = document.getElementById('klasse');
    if (!sel) return;
    var current = sel.value;

    function opt(parent, name) {
      var o = document.createElement('option');
      o.value = name; o.textContent = name;
      parent.appendChild(o);
    }

    sel.innerHTML = '';
    var ph = document.createElement('option');
    ph.value = ''; ph.textContent = '-- Klasse wählen --';
    sel.appendChild(ph);

    if (Array.isArray(d.classes) && d.classes.length) {
      d.classes.forEach(function (c) { opt(sel, c); });
    } else if (d.all_classes && typeof d.all_classes === 'object') {
      Object.keys(d.all_classes).forEach(function (group) {
        var og = document.createElement('optgroup');
        og.label = group;
        (d.all_classes[group] || []).forEach(function (c) { opt(og, c); });
        sel.appendChild(og);
      });
    }
    if (current) { sel.value = current; }
  }

  document.addEventListener('DOMContentLoaded', function () {
    // Codes an weiterfuehrende Links weitergeben
    if (window.TEACHER_CODE) {
      document.querySelectorAll('a[data-carry-t]').forEach(function (a) {
        var u = new URL(a.getAttribute('href'), location.href);
        u.searchParams.set('t', window.TEACHER_CODE);
        if (window.LESSON_CODE) { u.searchParams.set('l', window.LESSON_CODE); }
        a.setAttribute('href', u.pathname.split('/').pop() + u.search);
      });
    }

    var el = document.getElementById('teacherBanner');
    if (!el) return;
    if (!window.TEACHER_CODE) {
      el.className = 'alert alert-warning';
      el.style.display = 'block';
      el.textContent = 'Kein Lehrer-Link erkannt. Bitte öffne den Link, den dir deine Lehrperson gegeben hat.';
      return;
    }

    // Lehrername holen, danach optional Lektionstitel anhaengen.
    fetch('teacher_info.php?code=' + encodeURIComponent(window.TEACHER_CODE))
      .then(function (r) { return r.json(); })
      .then(function (d) {
        el.style.display = 'block';
        if (!d.success) {
          el.className = 'alert alert-warning';
          el.textContent = 'Dieser Lehrer-Link ist ungültig. Bitte bei der Lehrperson nachfragen.';
          return;
        }
        el.className = 'alert alert-info';
        el.textContent = 'Für: ' + d.name;

        // Klassen-Auswahlfeld immer vom Server befuellen – einzige Quelle ist
        // all_classes() in db.php. Hat die Lehrperson eine persoenliche Auswahl,
        // hat diese Vorrang; sonst die vollstaendige Stammliste (mit Gruppen).
        fillClasses(d);
        if (window.LESSON_CODE) {
          fetch('lesson_info.php?code=' + encodeURIComponent(window.LESSON_CODE))
            .then(function (r) { return r.json(); })
            .then(function (ld) {
              if (!ld.success) return;
              el.textContent = 'Für: ' + d.name + ' · Lektion: ' + ld.title;
              // Optionale Feedback-Frage der Lehrperson anzeigen (falls die Seite ein Feld dafuer hat)
              window.LESSON_QUESTION = ld.question || '';
              var q = document.getElementById('lessonQuestion');
              if (q && window.LESSON_QUESTION) {
                q.textContent = window.LESSON_QUESTION;
                q.style.display = 'block';
              }
            })
            .catch(function () {});
        }
      })
      .catch(function () {});
  });
})();
