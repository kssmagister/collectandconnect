// Gemeinsame Logik der Schueler-Seiten:
//  - liest den Lehrer-Code aus der URL (?t=CODE) -> window.TEACHER_CODE
//  - haengt den Code an Links mit [data-carry-t] an (Landing Page)
//  - zeigt einen Banner mit dem Lehrernamen (aus teacher_info.php)
(function () {
  var params = new URLSearchParams(location.search);
  window.TEACHER_CODE = params.get('t') || '';

  document.addEventListener('DOMContentLoaded', function () {
    // Code an weiterfuehrende Links weitergeben (Landing -> Formulare)
    if (window.TEACHER_CODE) {
      document.querySelectorAll('a[data-carry-t]').forEach(function (a) {
        var u = new URL(a.getAttribute('href'), location.href);
        u.searchParams.set('t', window.TEACHER_CODE);
        a.setAttribute('href', u.pathname.split('/').pop() + u.search);
      });
    }

    // Banner mit Lehrername / Warnung
    var el = document.getElementById('teacherBanner');
    if (!el) return;
    if (!window.TEACHER_CODE) {
      el.className = 'alert alert-warning';
      el.style.display = 'block';
      el.textContent = 'Kein Lehrer-Link erkannt. Bitte öffne den Link, den dir deine Lehrperson gegeben hat.';
      return;
    }
    fetch('teacher_info.php?code=' + encodeURIComponent(window.TEACHER_CODE))
      .then(function (r) { return r.json(); })
      .then(function (d) {
        el.style.display = 'block';
        if (d.success) {
          el.className = 'alert alert-info';
          el.textContent = 'Für: ' + d.name;
        } else {
          el.className = 'alert alert-warning';
          el.textContent = 'Dieser Lehrer-Link ist ungültig. Bitte bei der Lehrperson nachfragen.';
        }
      })
      .catch(function () {});
  });
})();
