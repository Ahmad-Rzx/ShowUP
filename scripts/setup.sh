#!/usr/bin/env bash
# ShowUp dev setup for Kali / Ubuntu / Debian.
#
# Installs PHP + Docker, starts MySQL 8.4 in a container, imports the schema and
# creates config/db.php. Safe to re-run: it never wipes data unless you ask.
#
#   ./scripts/setup.sh              normal setup
#   ./scripts/setup.sh --reset-db   drop and re-import the schema (WIPES local data)
set -euo pipefail

CONTAINER="showup-mysql"
VOLUME="showup-mysql-data"
IMAGE="mysql:8.4"
HOST_PORT=3307
DB_NAME="event_platform"
DB_USER="showup"

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SCHEMA="$ROOT_DIR/database/event_schema_v2.sql"
DB_PHP="$ROOT_DIR/config/db.php"
# Credentials live OUTSIDE the repo, so they can never be served by the dev web server or committed.
CRED_DIR="${XDG_CONFIG_HOME:-$HOME/.config}/showup"
CRED_FILE="$CRED_DIR/db-credentials"

RESET_DB=0
[[ "${1:-}" == "--reset-db" ]] && RESET_DB=1

say() { printf '\n==> %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

[[ -f "$SCHEMA" ]] || die "Schema not found at $SCHEMA"

# 1. Packages ---------------------------------------------------------------
say "Checking packages (docker, php-cli, php-mysql, git, openssl)"
if ! command -v docker >/dev/null 2>&1 \
   || ! command -v php >/dev/null 2>&1 \
   || ! php -r 'exit(extension_loaded("pdo_mysql") ? 0 : 1);' \
   || ! command -v openssl >/dev/null 2>&1; then
  sudo apt-get update
  # php-cli (not "php") so apt does not pull in Apache
  sudo apt-get install -y docker.io php-cli php-mysql git openssl
fi

# 2. Docker daemon ----------------------------------------------------------
sudo systemctl enable --now docker >/dev/null 2>&1 || true
DOCKER=(docker)
if ! docker info >/dev/null 2>&1; then
  DOCKER=(sudo docker)
  sudo usermod -aG docker "$USER" 2>/dev/null || true
  echo "Note: using sudo for Docker this run. Log out and back in once to use docker without sudo."
fi

container_exists() {
  "${DOCKER[@]}" ps -a --format '{{.Names}}' | grep -x "$CONTAINER" >/dev/null
}

# docker exec helper: runs a command inside the container with the root password
# passed through MYSQL_PWD (keeps it off the command line)
dexec() { "${DOCKER[@]}" exec -i -e MYSQL_PWD="$ROOT_PW" "$CONTAINER" "$@"; }

# 3. Credentials (generated once, reused on every re-run) --------------------
if [[ -f "$CRED_FILE" ]]; then
  # shellcheck disable=SC1090
  source "$CRED_FILE"
else
  if container_exists; then
    die "Container '$CONTAINER' exists but $CRED_FILE is missing. See README > 'Start over', then re-run."
  fi
  mkdir -p "$CRED_DIR"
  chmod 700 "$CRED_DIR"
  ROOT_PW="$(openssl rand -hex 16)"
  APP_PW="$(openssl rand -hex 16)"
  ( umask 077; printf 'ROOT_PW=%s\nAPP_PW=%s\n' "$ROOT_PW" "$APP_PW" > "$CRED_FILE" )
fi

# 4. MySQL container ---------------------------------------------------------
if container_exists; then
  say "Starting existing container '$CONTAINER'"
  "${DOCKER[@]}" start "$CONTAINER" >/dev/null
else
  say "Creating MySQL container ($IMAGE on 127.0.0.1:$HOST_PORT)"
  "${DOCKER[@]}" run -d --name "$CONTAINER" \
    -e MYSQL_ROOT_PASSWORD="$ROOT_PW" \
    -e MYSQL_DATABASE="$DB_NAME" \
    -e MYSQL_USER="$DB_USER" -e MYSQL_PASSWORD="$APP_PW" \
    -p "127.0.0.1:${HOST_PORT}:3306" \
    -v "$VOLUME:/var/lib/mysql" \
    --restart unless-stopped \
    "$IMAGE" >/dev/null
fi

say "Waiting for MySQL (first start can take about 30 seconds)"
READY=0
for _ in $(seq 1 90); do
  # -h127.0.0.1 only succeeds once the real server is up, not the temporary init server
  if dexec mysqladmin ping -h127.0.0.1 -uroot --silent >/dev/null 2>&1; then
    READY=1
    break
  fi
  sleep 2
done
[[ "$READY" == 1 ]] || die "MySQL did not become ready. Check: docker logs $CONTAINER"

# 5. Schema ------------------------------------------------------------------
HAS_USERS="$(dexec mysql -uroot -N -B -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='users'")"
if [[ "$HAS_USERS" == "0" || "$RESET_DB" == 1 ]]; then
  say "Importing database/event_schema_v2.sql"
  dexec mysql -uroot < "$SCHEMA"
else
  say "Schema already present, skipping import (use --reset-db to wipe and reload)"
fi

# 6. config/db.php -----------------------------------------------------------
if [[ ! -f "$DB_PHP" ]]; then
  say "Creating config/db.php"
  sed -e "s/CHANGE_ME/${APP_PW}/" "$ROOT_DIR/config/db.example.php" > "$DB_PHP"
  chmod 600 "$DB_PHP"
else
  say "config/db.php already exists, leaving it alone"
fi

# 7. Verify ------------------------------------------------------------------
say "Verifying PHP -> MySQL"
php "$ROOT_DIR/scripts/check_db.php"

cat <<MSG

All set. Next:
  cd "$ROOT_DIR"
  php -S localhost:8000          # then open http://localhost:8000/public/home.php
  ./scripts/mysql-shell.sh       # SQL prompt on the project database
MSG
