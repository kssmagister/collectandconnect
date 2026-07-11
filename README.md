# COLLECT & CONNECT

A lightweight feedback / structured-answer collection tool for school classes.
Students open a landing page, pick the form the teacher announced, and submit their answer.
Teachers review, filter, analyse and export everything through a password-protected admin panel.
An API endpoint lets an external machine (e.g. a home server running an AI analysis) pull the data.

## 🚀 Form types

All submissions live in **one** table (`submissions`) with a `form_type` discriminator and a
JSON `payload`. Adding a new form type later means adding one HTML page and one line in
`submit.php` — no database or backend rebuild.

| Page                | `form_type`    | Fields |
|---------------------|----------------|--------|
| `feedback.html`     | `feedback`     | free text (no visible question) |
| `exit_ticket.html`  | `exit_ticket`  | key insight · open question · confidence **1–5** · what would help *(optional)* |
| `strukturiert.html` | `strukturiert` | Was · Wann · Warum · Folgen · Beispiel *(optional)* |

Every form asks for a **class** (required) and a **nickname** (optional).

## 🧭 Pages

- `index.html` — landing page; students choose a form
- `feedback.html` / `exit_ticket.html` / `strukturiert.html` — the forms
- `input.html` — legacy URL, now just redirects to `index.html`
- `login.html` → `admin.php` — teacher login and dashboard

## 📋 Prerequisites

- PHP 8+ with `mysqli`
- MySQL 5.7+ / MariaDB 10.2+ (for the `JSON` column type)
- Apache (the included `.htaccess` protects `.env` and disables directory listing)
- HTTPS in production (session cookies use the `Secure` flag)

## 🔧 Installation

1. Clone and create your `.env` from the template:
   ```bash
   git clone https://github.com/kssmagister/collectandconnect.git
   cp .env.example .env      # then fill in real values
   ```
   `.env` is git-ignored **and** blocked from web access via `.htaccess`. Never commit secrets.
2. Create the table:
   ```bash
   mysql -u USER -p DBNAME < migrations/001_submissions.sql
   ```

## 🌐 Data export API (for external analysis)

`api_structured_data.php` is a read-only JSON endpoint, authenticated with an API key sent in
the **`X-API-Key` header** (value = `PYTHON_API_KEY` from `.env`). A machine on your own network
pulls the data — the public site never pushes anywhere.

```bash
curl -H "X-API-Key: $KEY" \
  "https://kss-latein.info/collectandconnect/api_structured_data.php?limit=1000"
```

Query parameters:

| Param       | Meaning |
|-------------|---------|
| `form_type` | only one type (e.g. `exit_ticket`); omit for all |
| `limit`     | max rows (default 1000, capped at 10000) |
| `since`     | only rows newer than this timestamp (incremental pulls) |

Each row contains the raw `payload` object plus a pre-joined `text` field (handy as LLM input).

## 📁 Project structure

```
collectandconnect/
├── .htaccess                 # Protects .env, disables listing
├── config.php                # Loads .env, session settings
├── db.php                    # DB connection + login helper (shared)
├── index.html                # Landing page
├── feedback.html             # Free-text feedback form
├── exit_ticket.html          # Exit ticket (4 questions incl. 1–5 scale)
├── strukturiert.html         # Structured Was/Wann/Warum/Folgen form
├── input.html                # Legacy redirect → index.html
├── submit.php                # Generic submission handler (all form types)
├── login.html / login.php    # Admin login (credentials from .env)
├── logout.php / check_login.php
├── admin.php                 # Unified admin: filter, stats, export, clear (CSRF)
├── feedback_view.php         # Beamer/classroom view of feedback (login-protected)
├── getSubmissions.php        # Data endpoint (login-protected)
├── clearSubmissions.php      # Delete (login-protected, optional per type)
├── api_structured_data.php   # API-key protected JSON endpoint
├── assets/style.css          # Shared styling
├── migrations/001_submissions.sql
├── img/  ·  LICENSE  ·  README.md
```

## 🔒 Security notes

Implemented:
- `.env` git-ignored and `.htaccess`-blocked; all SQL via prepared statements
- **Admin password stored as a bcrypt hash** (`ADMIN_PASSWORD_HASH`, verified with
  `password_verify`); plaintext `ADMIN_PASSWORD` still works as a fallback
- **Login rate limiting**: max 8 failed attempts per IP per 15 min (`login_attempts`
  table, auto-created), plus a small delay per failure
- **CSRF protection** on the destructive admin action (`clearSubmissions.php`):
  session token embedded in `admin.php`, sent as `X-CSRF-TOKEN`
- Session id regenerated on login (anti-fixation)
- All admin output is HTML-escaped (XSS); errors are logged, not shown to clients
- Data endpoints require an authenticated session; the API requires a header key

Still recommended:
- For AI analysis of minors' data, prefer a **local** model on your own server

## 📝 License

GNU General Public License v3.0 — see [LICENSE](LICENSE).
