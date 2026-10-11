# ShowUp

Event platform with commitment-based RSVPs. Web Technologies semester project
(HTML, CSS, JavaScript, PHP, MySQL). Team: Ahmad and Sultan. Weekly sync: Sundays.

The PHP structure follows the course guide (`PHP_authentication.md` in the course repo):
only `public/` is web-accessible; config and includes live outside it.

## Quick start (Kali, Ubuntu or Debian)

```bash
git clone <repo-url> showup && cd showup
./scripts/setup.sh
php -S localhost:8000 -t public
```

Then open http://localhost:8000/signup.php.

`setup.sh` installs PHP and Docker, runs **MySQL 8.4** in a container on
`127.0.0.1:3307`, imports `database/event_schema_v2.sql`, creates your local
`config/database.php` and checks that PHP can reach the database. It is safe to
re-run and never wipes data unless you pass `--reset-db`.

Why Docker? Kali's `mysql` packages are MariaDB, and MySQL 8.0 is end-of-life.
The container gives both of us the same supported MySQL on any machine.

## Daily use

| Task | Command |
|---|---|
| Run the site | `php -S localhost:8000 -t public` from the repo root |
| Health check | `php scripts/check_db.php` |
| SQL prompt | `./scripts/mysql-shell.sh` |
| Start the DB if it is stopped | `docker start showup-mysql` |
| Reload the schema (wipes local data) | `./scripts/setup.sh --reset-db` |

## Structure

```
config/
  database.example.php   committed template
  database.php           your local copy (gitignored, created by setup.sh)
includes/
  session.php            starts the session safely (include FIRST on every page)
  auth.php               protects a page: not logged in -> login.php
  helpers.php            e() escaping, CSRF helpers, redirect()
  header.php, footer.php shared page layout (Sultan)
public/                  the web root: only these files are reachable from the browser
  index.php              redirects to home.php or login.php
  signup.php, login.php, logout.php, home.php
  assets/css, assets/js, assets/img
database/                event_schema_v2.sql
scripts/                 setup.sh, mysql-shell.sh, check_db.php
```

## Team agreements (follow the course guide)

- **Database:** MySQLi with prepared statements. Pages do
  `require_once __DIR__ . '/../config/database.php';` and get `$conn`.
- **Session:** every page starts with `require_once __DIR__ . '/../includes/session.php';`
  before any output. Protected pages use `require_once __DIR__ . '/../includes/auth.php';` instead.
- **Session keys after login:** `$_SESSION['user_id']`, `$_SESSION['user_name']`,
  `$_SESSION['user_email']`. Call `session_regenerate_id(true);` right after `password_verify()` succeeds.
- **Login errors:** always the generic "Invalid email or password." (never say which one was wrong).
- **Redirects:** signup -> `login.php?registered=1` (login shows "Account created, please log in");
  login -> `home.php`; logout -> `login.php`. Put `exit;` after every `header('Location: ...')`.
- **Logout:** clear `$_SESSION`, expire the session cookie, `session_destroy()`, redirect (guide section 46).
- **Output:** wrap anything from users or the database in `e()` (our short name for `htmlspecialchars`).
- **Forms:** every POST form contains `<?= csrf_field() ?>` and the handler checks `csrf_verify($_POST['csrf_token'] ?? null)`.
- **Pages** are `.php` files containing plain HTML. Each form posts to its own file.
- **Paths** inside `public/` are relative: `assets/css/style.css`, `login.php`.

## Git workflow

- `main` always works. Work on your own branch (`feature/signup`, `feature/login`) and merge by pull request.
- Pull before you start work each day. Small commits with clear messages.
- Never commit `config/database.php` (it is gitignored) or any password.

## Troubleshooting

- **"Connection refused" from PHP:** the container is stopped. Run `docker start showup-mysql`.
- **Port 3307 already in use:** something else holds it. Stop that, or change `HOST_PORT` in
  `scripts/setup.sh` and `$port` in `config/database.php`.
- **"permission denied" talking to Docker:** log out and back in once after the first setup, or prefix with `sudo`.
- **PHP can't find the MySQL driver:** `sudo apt install php-mysql`.
- **"Headers already sent":** something printed output before `session_start()` or `header()`. Make the
  PHP block at the very top of the file, with nothing (not even a blank line) before `<?php`.
- **Always use `127.0.0.1`, not `localhost`,** as the DB host. `localhost` makes PHP look for a Unix socket.

## Start over (full clean slate)

Your DB credentials live outside the repo in `~/.config/showup/db-credentials`.

```bash
docker rm -f showup-mysql && docker volume rm showup-mysql-data
rm -rf ~/.config/showup config/database.php
./scripts/setup.sh
```
