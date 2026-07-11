// Gemeinsame Logik der Schueler-Seiten:
//  - liest Lehrer-Code (?t=) und optionalen Lektions-Code (?l=) aus der URL
//  - haengt beide an Links mit [data-carry-t] an (Landing -> Formulare)
//  - zeigt einen Banner mit Lehrername (+ Lektionstitel, falls vorhanden)
(function () {
  var params = new URLSearchParams(location.search);
  window.TEACHER_CODE = params.get('t') || '';
  window.LESSON_CODE = params.get('l') || '';

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
        if (window.LESSON_CODE) {
          fetch('lesson_info.php?code=' + encodeURIComponent(window.LESSON_CODE))
            .then(function (r) { return r.json(); })
            .then(function (ld) {
              if (ld.success) { el.textContent = 'Für: ' + d.name + ' · Lektion: ' + ld.title; }
            })
            .catch(function () {});
        }
      })
      .catch(function () {});
  });
})();
