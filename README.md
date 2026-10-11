# ShowUp

Event platform with commitment-based RSVPs. Web Technologies semester project
(HTML, CSS, JavaScript, PHP, MySQL). Team: Ahmad and Sultan. Weekly sync: Sundays.

## Quick start (Kali, Ubuntu or Debian)

```bash
git clone <repo-url> showup && cd showup
./scripts/setup.sh
php -S localhost:8000
```

`setup.sh` installs PHP and Docker, runs **MySQL 8.4** in a container on
`127.0.0.1:3307`, imports `database/event_schema_v2.sql`, creates your local
`config/db.php` and checks that PHP can reach the database. It is safe to
re-run and never wipes data unless you pass `--reset-db`.

Why Docker? Kali's `mysql` packages are MariaDB, and MySQL 8.0 is end-of-life.
The container gives both of us the same supported MySQL on any machine.

## Daily use

| Task | Command |
|---|---|
| Run the site | `php -S localhost:8000` from the repo root |
| Health check | `php scripts/check_db.php` |
| SQL prompt | `./scripts/mysql-shell.sh` |
| Start the DB if it is stopped | `docker start showup-mysql` |
| Reload the schema (wipes local data) | `./scripts/setup.sh --reset-db` |

## Structure

```
config/        db.example.php (committed), db.php (local, gitignored)
includes/      shared PHP: auth_guard.php, header.php, footer.php
auth/          signup.php, login.php, logout.php
public/        home.php and later pages
assets/        css/, js/, img/
database/      event_schema_v2.sql
scripts/       setup.sh, mysql-shell.sh, check_db.php
```

## Team agreements

- Pages are `.php` files containing plain HTML; each form posts to its own file.
- Every page gets the database with `require_once __DIR__ . '/../config/db.php';` (provides `$pdo`).
- After login we store `$_SESSION['user_id']` and `$_SESSION['full_name']`.
- `includes/auth_guard.php` redirects to `auth/login.php` when `user_id` is not in the session.
- Successful login redirects to `public/home.php`.
- PDO prepared statements for every query. `password_hash()` / `password_verify()` for passwords.
  `htmlspecialchars()` on all output. `session_regenerate_id(true)` after login.

## Git workflow

- `main` always works. Work on your own branch (`feature/signup`, `feature/login`) and merge by pull request.
- Pull before you start work each day. Small commits with clear messages.
- Never commit `config/db.php` (it is gitignored) or any password.

## Troubleshooting

- **"Connection refused" from PHP:** the container is stopped. Run `docker start showup-mysql`.
- **Port 3307 already in use:** something else holds it. Stop that, or change `HOST_PORT` in
  `scripts/setup.sh` and `$dbPort` in `config/db.php`.
- **"permission denied" talking to Docker:** log out and back in once after the first setup, or prefix with `sudo`.
- **`php` can't find the MySQL driver:** `sudo apt install php-mysql`.
- **Always use `127.0.0.1`, not `localhost`,** as the DB host. `localhost` makes PHP look for a Unix socket.

## Start over (full clean slate)

Your DB credentials live outside the repo in `~/.config/showup/db-credentials`.

```bash
docker rm -f showup-mysql && docker volume rm showup-mysql-data
rm -rf ~/.config/showup config/db.php
./scripts/setup.sh
```
