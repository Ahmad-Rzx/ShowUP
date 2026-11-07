# Walkthrough of `demo.sh`

## What it is for

`scripts/demo.sh` is an optional presentation helper. It loads the sample
host and events by running `database/seed.sql`, then prints a suggested
walkthrough for demonstrating ShowUp.

It was added to meet the Day 4 checklist item asking for a demo script. It
does not replace the other scripts:

| Script | Purpose |
|---|---|
| `scripts/setup.sh` | Installs/configures the local environment and database. |
| `scripts/check_db.php` | Checks that PHP can connect to MySQL and that the schema is present. |
| `scripts/mysql-shell.sh` | Opens a MySQL command prompt. |
| `scripts/demo.sh` | Loads demo data and prints presentation steps. |

## How to use it

From the project root, run:

```bash
./scripts/demo.sh
```

It asks for the MySQL `showup` user's password. It then imports the demo seed
data and prints the demo login and suggested presentation flow. The seed can
be run again without duplicating the sample events.

Start the PHP development server separately:

```bash
php -S localhost:8000 -t public
```

Then open `http://localhost:8000` in your browser.

For native MySQL configured on port `3306`, run:

```bash
MYSQL_PORT=3306 ./scripts/demo.sh
```

The script defaults to host `localhost`, port `3307`, database `event_platform`,
and MySQL user `showup`. These can be overridden with `MYSQL_HOST`,
`MYSQL_PORT`, `MYSQL_DATABASE`, and `MYSQL_USER`.

## Suggested presentation flow

1. Sign in as `demo.host@showup.test` with password `ShowUpDemo2026`.
2. Show the hosted sample events in the Host dashboard.
3. Create a test event starting about 15 minutes from now.
4. Sign in to a second account in a private browser window, RSVP, and confirm.
5. Check in while the test event is in progress, then submit a review.

The seeded events are scheduled in the future for browsing, so create a
near-term test event to demonstrate check-in and reviews. The demo credentials
and placeholder online links are for local presentation only.
