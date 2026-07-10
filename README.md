# COLLECT & CONNECT

A lightweight feedback / structured-answer collection tool for school classes.
Students submit a structured answer (Nickname + class + *Was / Wann / Warum / Folgen / Beispiel*);
teachers review, filter and export the entries through a password-protected admin panel.

> **Note:** The original free-text version (`memoranda` table) has been retired.
> The app now uses the structured system (`memoranda_structured`) exclusively.

## 🚀 Features

- **Structured submission** (`input.html` → `submit_collect.php`)
  - Nickname + class selection
  - Guided fields: *Was / Wann / Warum / Folgen* (+ optional *Beispiel/Quelle*)
  - Character counters and inline tips
- **Admin panel** (`admin_structured.html`)
  - Session-based login (credentials from `.env`)
  - Filter by class / nickname, sort by date
  - Statistics (entries, students, classes, avg. length)
  - CSV / JSON export, clear database
- **Machine API** (`api_structured_data.php`)
  - Read-only JSON endpoint for external tools (e.g. a Python analysis script)
  - Authenticated via `X-API-Key` **header** (from `.env`)

## 📋 Prerequisites

- PHP 8+ with the `mysqli` extension
- MySQL / MariaDB
- Apache (the included `.htaccess` protects `.env` and blocks directory listing)
- HTTPS in production (session cookies are set with the `Secure` flag)

## 🔧 Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/kssmagister/collectandconnect.git
   ```
2. Create your `.env` from the template and fill in real values:
   ```bash
   cp .env.example .env
   ```
   ```env
   DB_HOST=your_database_host
   DB_NAME=your_database_name
   DB_USER=your_database_user
   DB_PASSWORD=your_database_password

   ADMIN_USERNAME=your_admin_username
   ADMIN_PASSWORD=your_admin_password

   PYTHON_API_KEY=your_api_key      # only if you use api_structured_data.php
   ```
   > `.env` is git-ignored and additionally blocked from web access via `.htaccess`.
   > **Never commit real credentials.**
3. Create the database table:
   ```sql
   CREATE TABLE memoranda_structured (
       id        INT AUTO_INCREMENT PRIMARY KEY,
       nickname  VARCHAR(100) NOT NULL,
       auswahl   VARCHAR(50)  NOT NULL,   -- class
       was       TEXT,
       wann      VARCHAR(255),
       warum     TEXT,
       folgen    TEXT,
       beispiel  TEXT,
       timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
   );
   ```

## 📁 Project Structure

```
collectandconnect/
├── .env.example                     # Template for environment config
├── .gitignore
├── .htaccess                        # Protects .env, blocks listing
├── config.php                       # Loads .env, session + DB settings
├── input.html                       # Public structured submission form
├── submit_collect.php               # Stores a submission (prepared statement)
├── login.html / login.php           # Admin login (credentials from .env)
├── logout.php / check_login.php     # Session helpers
├── admin_structured.html            # Admin dashboard (filter, stats, export)
├── getEntries_structured.php        # Data endpoint (login-protected)
├── clearDB_memoranda_structured.php # Wipes the table (login-protected)
├── api_structured_data.php          # API-key protected JSON endpoint
├── img/                             # Screenshots (may be outdated)
├── LICENSE
└── README.md
```

## 🔒 Security notes

Implemented:
- Environment-based configuration; `.env` git-ignored and `.htaccess`-blocked
- All SQL via prepared statements (`mysqli`)
- Admin credentials compared with `hash_equals`; session id regenerated on login
- Output in the admin panel is HTML-escaped (XSS)
- Data endpoints require an authenticated session; API endpoint requires a header key
- Errors are logged, not displayed to the client

Still recommended (see project notes):
- Hash the admin password instead of storing it in `.env` as plaintext
- Add CSRF tokens to state-changing POSTs (login, clear-DB)
- Add rate limiting / lockout on the login endpoint

## 📝 License

GNU General Public License v3.0 — see [LICENSE](LICENSE) or
<https://www.gnu.org/licenses/gpl-3.0.html>.
