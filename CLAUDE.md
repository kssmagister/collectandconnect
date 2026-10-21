# CLAUDE.md – Projektkontext COLLECT & CONNECT

## Was ist dieses Projekt?

Webbasiertes **Feedback-/Antworterfassungs-Tool für Schüler** (Kantonsschule). Lehrpersonen
sammeln strukturierte Rückmeldungen, werten sie im Admin aus und projizieren sie (Beamer/
Wortwolke). Die Daten werden zusätzlich von der **DRP** (KI-Reflexionsplattform auf einem
privaten Ubuntu-Server) per API abgeholt.

- **Live:** `https://kss-latein.info/collectandconnect/`
- **GitHub:** `https://github.com/kssmagister/collectandconnect` — ⚠️ **PUBLIC!**
- **Sprache:** Chat/Erklärungen und Commit-Messages auf Deutsch.

---

## Tech-Stack

- **PHP 8+**, prozedural (ein File pro Aktion), **mysqli** mit Prepared Statements
- **Bootstrap 4.5 + jQuery 3.5** via CDN, kein Build-Step, kein Composer
- Konfiguration über `.env` (gitignored, per `.htaccess` vor Web-Zugriff geschützt)

## Datenbank (`rutzimp_pensum2023`, User `rutzimp_pensat`)

```sql
teachers   (id, username UNIQUE, password_hash [bcrypt], name, code UNIQUE, is_admin,
            classes [JSON-Array, persoenliche Klassenauswahl; NULL = alle], created_at)
lessons    (id, teacher_id, code UNIQUE, title, feedback_question, created_at,
            analysis_requested, analysis_requested_at,
            last_report_url NULL, last_report_at NULL)   -- 008, siehe unten
submissions(id, teacher_id, lesson_id NULL, form_type, klasse, nickname NULL,
            payload JSON, created_at)
login_attempts (id, ip, created_at)          -- Rate-Limit, legt login.php selbst an
```

Migrationen 001–008 liegen in `migrations/` und sind **alle ausgeführt** (007 `teachers.classes`
und 008 am 2026-10-21 in phpMyAdmin geprüft).
`form_type`: `feedback` | `exit_ticket` | `strukturiert`; die typ-spezifischen Felder
stehen als JSON in `payload`.

✅ **008 (`last_report_url`/`last_report_at` auf `lessons`) ist ausgefuehrt** (in
phpMyAdmin geprueft am 2026-10-21: beide Spalten existieren in `rutzimp_pensum2023`).
Hinweis fuer kuenftige Migrationen: `lessons.php` selektiert die Spalten direkt, ohne
Fallback – additive Migrationen immer *vor* dem Deploy ausfuehren.

---

## Kern-Konzepte

- **Mehrbenutzer + Daten-Trennung:** Jede Lehrperson hat ein Konto und sieht **nur eigene**
  Daten (überall `WHERE teacher_id = current_teacher_id()`). Ein Konto hat `is_admin`
  und verwaltet über `teachers.php` die anderen.
- **Persönlicher Link:** Schüler öffnen `?t=<LEHRER-CODE>` bzw. `?t=…&l=<LEKTIONS-CODE>`.
  `assets/common.js` liest die Codes, reicht sie an die Formularlinks weiter und zeigt
  einen Banner (Name/Lektion, via `teacher_info.php` / `lesson_info.php`).
  `submit.php` löst die Codes zu `teacher_id`/`lesson_id` auf.
- **Neuer Fragetyp** = 1 HTML-Seite + 1 Eintrag in `$schemas` in `submit.php`. Sonst nichts.
- **Klassen – einzige Quelle ist `all_classes()` in `db.php`.** Nirgends sonst eine
  Klassenliste pflegen! Verteilung:
  - Schüler-Formulare: `<select id="klasse">` enthält **nur** den Platzhalter;
    `teacher_info.php` liefert `all_classes` (+ ggf. persönliche Auswahl),
    `assets/common.js` (`fillClasses`) baut die Optionen.
  - `classes.php`, Admin- und Beamer-Filter: serverseitig aus `all_classes()`.
  - Jede Lehrperson kann in `classes.php` eine **persönliche Auswahl** speichern
    (`teachers.classes`, JSON; leer = alle). Sie hat Vorrang vor der Stammliste.
- **Sicherheit:** bcrypt (`password_verify`), CSRF (`csrf_token()`/`require_csrf()` in `db.php`,
  Header `X-CSRF-TOKEN`), Login-Rate-Limit (8/15 min pro IP), `session_regenerate_id`,
  Output HTML-escaped, Fehler werden geloggt statt angezeigt.
- **DRP-Anbindung:** `api_structured_data.php` (API-Key im Header `X-API-Key` = `PYTHON_API_KEY`;
  Filter `teacher`, `lesson`, `form_type`, `since`, `limit`) und
  `api_analysis_queue.php` (Pull-Warteschlange: „KI-Auswertung anfordern" im Web →
  Ubuntu-Server holt sie ab → `POST action=done[&report_url=...]`). Ist `report_url`
  dabei (gueltiger URL, sonst ignoriert), landet er in `lessons.last_report_url`/
  `last_report_at` und `lessons.php` zeigt „📄 Bericht ansehen" statt dass die
  Markierung spurlos verschwindet.

## Wichtige Dateien

```
index.html                Landing (Kachelauswahl)
feedback.html / exit_ticket.html / strukturiert.html   Schüler-Formulare
submit.php                generischer Submit für alle Fragetypen
login.html/.php, logout.php, check_login.php
admin.php                 Admin (Filter, Statistik, Export, Löschen, Share-Link)
feedback_view.php         Beamer-Ansicht: Karten (einspaltig) + Wortwolke (wordcloud2 CDN)
lessons.php / lesson_manage.php / lesson_info.php      Lektionen + Feedback-Frage
classes.php / class_manage.php                         persoenliche Klassenauswahl
teachers.php / teacher_manage.php / teacher_info.php   Konten (nur Admin)
getSubmissions.php, clearSubmissions.php               Daten (login-geschützt)
api_structured_data.php, api_analysis_queue.php        für die DRP
config.php (lädt .env), db.php (db(), require_login(), require_admin(), csrf_*)
assets/style.css, assets/common.js
```

---

## Konventionen

- **Sichtbare Texte** (UI, Alerts, Fehlermeldungen): echte Umlaute (ä, ö, ü, ß).
- **Code-Interna** (Variablen, Funktionen, DB-Spalten, URLs, Kommentare): ASCII (ae, oe, ue).
- Commit-Messages **auf Deutsch** mit echten Umlauten.
- Escaping: PHP `htmlspecialchars((string)$s, ENT_QUOTES)` (`h()`), JS `esc()`.

## Deployment

- **GitHub Action** (`.github/workflows/deploy.yml`) → FTPS bei Push auf `main`.
- **`server-dir: ./`** — das FTP-Konto ist bereits auf den `collectandconnect`-Ordner
  gechrootet. (Nicht „korrigieren"!)
- Ausgeschlossen: `.github`, `migrations`, `README.md`, `CLAUDE.md`, `LICENSE`, `img`, `.env`.
- **Migrationen werden manuell** in phpMyAdmin ausgeführt.

---

## ⚠️ Teuer erkaufte Lektionen (bitte beachten)

1. **Repo ist PUBLIC:** Niemals echte Secrets committen – auch **keine bcrypt-Hashes** in
   Migrationsdateien (Platzhalter nutzen, echten Wert per UPDATE im Chat/phpMyAdmin setzen).
   `.env` bleibt gitignored.
2. **`config.php` legt die DB-Zugangsdaten in die globalen `$username`/`$password`**, die
   `db()` per `global` nutzt. In Endpunkten **diese Namen nicht überschreiben** (z.B.
   Formulareingaben `$inUser`/`$inPass` nennen) – sonst „Access denied … using password: NO".
3. **Migrations-Reihenfolge:** *Additive* Migrationen (neue Tabelle/NULL-Spalte) **vor** dem
   Deploy ausführen → keine Ausfallzeit. Destruktive/NOT-NULL-Änderungen brauchen die
   Reihenfolge Deploy↔Migration abgestimmt.
4. **Rate-Limit:** Hostpoint hat einen **Proxy** – `REMOTE_ADDR` ist für alle gleich.
   Deshalb nutzt `login.php` `X-Forwarded-For` (erste IP). Sonst sperrt ein Nutzer alle aus.
5. **mysqli wirft ab PHP 8.1 Exceptions** statt `false` zurückzugeben. `@`-Operator und
   `if (!$stmt)` greifen nicht → **try/catch** verwenden (z.B. Rate-Limit „fail-open").
6. **Umlaute:** DB/Verbindung ist `utf8mb4` (`$conn->set_charset('utf8mb4')` in `db()`).
   Kauderwelsch in der Windows-Konsole ist nur ein Anzeigeproblem, kein Datenfehler.

## Verwandtes Projekt

**DRP** (Didactic Reflection Platform): lokales Python-Tool auf dem Ubuntu-Server
(`~/drp`), zieht Feedback über die API, analysiert es
mit **lokalem Ollama** (Datenschutz!) und erzeugt belegte Reflexionsberichte.
Siehe dort `README.md` und `ROADMAP.md`.

### Schnittstelle collectandconnect ⇄ DRP

Zwei getrennte Repos/Server (Datenschutz: Schülerdaten werden nur lokal ausgewertet).
Die Kopplung läuft **ausschliesslich** über diese API (Hostpoint, `https://…/collectandconnect/`),
Authentifizierung per Header `X-API-Key` (= `PYTHON_API_KEY` in der `.env` von collectandconnect,
`DRP_API_KEY` in der `.env` von DRP; nie per GET-Parameter).

| Endpunkt | Methode | Zweck |
|---|---|---|
| `api_structured_data.php` | GET | Rückmeldungen holen. Filter: `form_type`, `teacher` (Lehrer-Code), `lesson` (Lektions-Code), `since`, `limit`. Liefert u. a. `lesson_code`, `lesson_title`, `klasse`, `nickname`, `payload`, `text`, `created_at`. |
| `api_analysis_queue.php` | GET | Offene Auswertungs-Anforderungen: `[{lesson_code, title, teacher_code}]`. |
| `api_analysis_queue.php` | POST `action=done&lesson=CODE[&report_url=URL]` | Anforderung erledigt; optionale `report_url` (muss gültige URL sein, sonst ignoriert) landet in `lessons.last_report_url`/`last_report_at`. |

**Ablauf:** Lehrperson klickt in `lessons.php` „KI-Auswertung anfordern" (`lessons.analysis_requested=1`)
→ DRP fragt per Cron (alle 5 Min., Pull – der Heimserver ist von aussen nicht erreichbar) die Queue ab
→ holt die Rückmeldungen, erzeugt den Bericht lokal (Ollama) → meldet `done` + Bericht-Link zurück
(Link zeigt auf `DRP_WEB_BASE_URL`, nur im Heimnetz erreichbar).

**Voraussetzungen im Schema (collectandconnect-Migrationen):** `005` Queue-Spalten auf `lessons`,
`008` `lessons.last_report_url`/`last_report_at`. `lessons.php` selektiert diese Spalten direkt, ohne
Fallback.

**Bei Änderungen an der Schnittstelle (Reihenfolge):**
1. Additive Migration in phpMyAdmin ausführen (vor dem Deploy).
2. collectandconnect deployen (Push auf `main` → GitHub Action → FTPS).
3. Erst dann DRP anpassen/neu bauen (`docker compose build drp`).
Felder der API nur **ergänzen**, nicht umbenennen/entfernen, solange die andere Seite noch die alte Version fährt.
Commit-Messages verweisen auf das Gegenstück im anderen Repo.
