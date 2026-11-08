#!/usr/bin/env bash
# Seeds local demo events and prints the short presentation walkthrough.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MYSQL_HOST="${MYSQL_HOST:-localhost}"
MYSQL_PORT="${MYSQL_PORT:-3307}"
MYSQL_USER="${MYSQL_USER:-showup}"
MYSQL_DATABASE="${MYSQL_DATABASE:-event_platform}"

command -v mysql >/dev/null 2>&1 || {
  echo "ERROR: mysql client not found. Install the MySQL client first." >&2
  exit 1
}

[[ -f "$ROOT_DIR/database/seed.sql" ]] || {
  echo "ERROR: database/seed.sql not found." >&2
  exit 1
}

echo "Loading the ShowUp demo host and sample events into $MYSQL_DATABASE..."
mysql --protocol=TCP -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p \
  "$MYSQL_DATABASE" < "$ROOT_DIR/database/seed.sql"

cat <<'EOF'

Demo data is ready. Start the PHP server with:
  php -S localhost:8000 -t public

Presentation flow:
  1. Sign in as demo.host@showup.test / ShowUpDemo2026.
  2. Open Host dashboard and show the hosted sample events.
  3. Create a test event starting about 15 minutes from now.
  4. Sign in as a second account in a private window, RSVP and confirm.
  5. Check in while the event is in progress, then submit a review.

The sample events are scheduled later, so use the new test event for check-in.
The demo account password and online meeting URLs are for local presentation only.
EOF
