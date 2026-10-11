#!/usr/bin/env bash
# Opens a MySQL prompt inside the container, as root, on the project database.
set -euo pipefail

CONTAINER="showup-mysql"
DB_NAME="event_platform"
CRED_FILE="${XDG_CONFIG_HOME:-$HOME/.config}/showup/db-credentials"

[[ -f "$CRED_FILE" ]] || { echo "No credentials found. Run ./scripts/setup.sh first." >&2; exit 1; }
# shellcheck disable=SC1090
source "$CRED_FILE"

DOCKER=(docker)
docker info >/dev/null 2>&1 || DOCKER=(sudo docker)

exec "${DOCKER[@]}" exec -it -e MYSQL_PWD="$ROOT_PW" "$CONTAINER" mysql -uroot "$DB_NAME"
