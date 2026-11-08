# ShowUp

ShowUp is a small event platform built around commitment-based RSVPs. People can
discover events, reserve a spot, confirm their attendance, check in on the day,
and leave a review afterwards. Hosts get a dashboard with attendee lists, check-in
codes, and reliability labels for the people who signed up.

This is a semester project for Web Technologies, built with plain HTML, CSS,
JavaScript, PHP, and MySQL.

## Features

- Account signup, login, logout, and profile management
- Browse upcoming events with search, category, city, and mode filters
- Physical and online events, with online links shown only to confirmed attendees
- Event creation, editing, and cancellation for hosts
- Capacity-aware RSVPs with an automatic waitlist
- FIFO waitlist promotion when a spot opens
- A confirmation step before the event starts
- Check-in with a six-character code during the event window
- One rating and review per attendee after check-in
- Reliability labels calculated from confirmed and attended events
- My Events page with upcoming and past RSVPs
- Host dashboard with events, attendee lists, and check-in codes

## Tech stack

| Layer | Technology |
|---|---|
| Backend | PHP 8 with MySQLi prepared statements |
| Database | MySQL 8.4 |
| Frontend | HTML, CSS, vanilla JavaScript |
| Local database option | Docker container running MySQL 8.4 |
| Server | PHP built-in development server |

No PHP framework or build step is required.

## Requirements

- Linux (Kali, Ubuntu, or Debian recommended)
- PHP 8.0 or newer with the `mysqli` extension
- MySQL 8.4, either natively or through Docker
- `git`
- The `mysql` client only if you want to import seed data or migrations manually

To check your PHP setup:

```bash
php -v
php -m | grep mysqli
```

## Quick start with Docker

From the project root:

```bash
git clone <repo-url> showup
cd showup
./scripts/setup.sh
php -S localhost:8000 -t public
```

Then open:

```text
http://localhost:8000
```

`scripts/setup.sh` installs the required packages, starts a MySQL 8.4 container
named `showup-mysql`, publishes it on `127.0.0.1:3307`, imports the schema, and
creates the local `config/database.php` file. It is safe to re-run and does not
wipe data unless you pass `--reset-db`.

Docker is the recommended path on Kali because its `mysql` packages are MariaDB,
not MySQL. The container also gives every machine the same database version.

## Manual setup with native MySQL

If MySQL 8.4 is already installed and listening on port `3306`:

```bash
cp config/database.example.php config/database.php
```

Edit `config/database.php` and set your own host, port, username, password, and
database name. For a native MySQL install, change the port to `3306`.

Import the schema:

```bash
mysql -u showup -p event_platform < database/event_schema_v2.sql
```

Then verify the connection:

```bash
php scripts/check_db.php
```

Finally, start the server:

```bash
php -S localhost:8000 -t public
```

`config/database.php` is gitignored. Never commit real database credentials.

## Database

The application database is called `event_platform`. The fresh-install schema is
in `database/event_schema_v2.sql`.

For a database created before the event-mode feature, run the migration once:

```bash
mysql -u showup -p event_platform < database/migrations/2026_11_05_event_modes.sql
```

To confirm the schema and PHP connection:

```bash
php scripts/check_db.php
```

A successful check prints the PHP version, MySQL version, detected tables, the
number of seeded categories, and an `OK` message.

## Demo data

Load the demo host and sample events:

```bash
./scripts/demo.sh
```

The script uses the Docker defaults (`localhost:3307`, user `showup`, database
`event_platform`) and prompts for the password. Override the connection for a
different setup:

```bash
MYSQL_PORT=3306 MYSQL_USER=showup ./scripts/demo.sh
```

You can also import the seed file directly:

```bash
mysql -h localhost -P 3307 -u showup -p event_platform < database/seed.sql
```

The seed file is safe to re-run and does not duplicate its sample events.

Demo login:

```text
Email:    demo.host@showup.test
Password: ShowUpDemo2026
```

The demo password and the online meeting URLs are for local development only.

## Demo walkthrough

1. Start the server with `php -S localhost:8000 -t public`.
2. Sign in as `demo.host@showup.test` with password `ShowUpDemo2026`.
3. Open the Host dashboard and show the hosted sample events.
4. Create a test event that starts about 15 minutes from now.
5. Sign in as a second account in a private window and RSVP.
6. Confirm the RSVP, then check in while the event is in progress.
7. Submit a review and show the updated reliability label.

The seeded sample events are scheduled a few days ahead, so they are best for
browsing. Use the newly created event for the check-in and review flow.

## Project structure

```text
config/
  database.example.php   database template, committed
  database.php           local connection settings, gitignored

includes/
  session.php            safe session start, include first
  auth.php               protects pages that require login
  helpers.php            escaping, CSRF, redirects, flash messages
  event_helpers.php      event queries, validation, dates, reliability
  event_form.php         shared create and edit form
  header.php             shared page header and navigation
  footer.php             shared page footer
  errors.php             friendly 403 and 404 pages

public/                  web root, the only browser-accessible directory
  index.php              redirects to the home page or login
  signup.php             account creation
  login.php              login form and handler
  logout.php             ends the session
  home.php               landing page after login
  events.php             browse, search, and filter events
  event.php              event details, check-in, and reviews
  event-create.php       create an event
  event-edit.php         edit or cancel an event, host only
  my-events.php          the user's upcoming and past RSVPs
  rsvp.php               RSVP, waitlist, and cancellation handler
  confirm.php            confirms an RSVP before the event
  checkin.php            attendee check-in handler
  review.php             review submission after check-in
  profile.php            profile, attended events, and hosted events
  host-dashboard.php     host events, codes, and attendee lists
  assets/                CSS, JavaScript, and images

database/
  event_schema_v2.sql    fresh-install schema
  seed.sql               demo host and sample events
  migrations/            changes for existing databases

scripts/
  setup.sh               installs dependencies and prepares Docker MySQL
  check_db.php           verifies PHP, MySQL, and the schema
  mysql-shell.sh         opens a SQL prompt for the project database
  demo.sh                loads demo data and prints the walkthrough
```

## Event rules

Events are either `physical` or `online`. Physical events store a venue, address,
and city. Online events store a meeting link that is shown only to the host and
to attendees who have confirmed.

A registration moves through these states:

- `registered`: the user has an active spot
- `waitlisted`: the event was full when the user tried to RSVP
- `cancelled`: the user cancelled or the host cancelled the event

Capacity counts only `registered` rows. When a registered attendee cancels, the
oldest waitlisted attendee is promoted automatically. Waitlist order is first in,
first out.

Check-in opens 30 minutes before the start time and closes when the event ends.
An attendee must have confirmed before checking in.

Reviews accept one rating from 1 to 5 per user and event, and only after that
user has checked in. Reliability is calculated as `attended / confirmed` from the
`user_reliability` view. Users with fewer than three confirmed events are shown
as `New`. After that, the label is Highly reliable (90 percent or more),
Reliable (70 percent or more), Sometimes reliable (40 percent or more), or
Unreliable.

## Security notes

- All SQL uses MySQLi prepared statements.
- All POST forms include a CSRF token, and handlers verify it.
- Pages that require login use `includes/auth.php`.
- Event editing and cancellation verify host ownership.
- User and database content is escaped with `e()` before HTML output.
- Login errors stay generic and never reveal whether the email or password was wrong.
- The session ID is regenerated after a successful login.
- Database credentials live in the gitignored `config/database.php`.

## Common commands

| Task | Command |
|---|---|
| Start the site | `php -S localhost:8000 -t public` |
| Check the database | `php scripts/check_db.php` |
| Open a SQL prompt | `./scripts/mysql-shell.sh` |
| Start a stopped Docker database | `docker start showup-mysql` |
| Load demo data | `./scripts/demo.sh` |
| Reset the Docker database | `./scripts/setup.sh --reset-db` |

## Troubleshooting

**The database connection is refused**

The Docker container is probably stopped:

```bash
docker start showup-mysql
```

Then run `php scripts/check_db.php` again.

**Port 3307 is already in use**

Stop the process using the port, or change `HOST_PORT` in `scripts/setup.sh` and
the port in `config/database.php`.

**Docker reports a permission error**

Log out and back in once after the first setup, or run the Docker commands with
`sudo`.

**PHP cannot find the MySQL driver**

```bash
sudo apt install php-mysql
```

**Headers already sent**

Something printed output before `session_start()` or `header()`. Keep the opening
`<?php` block at the very top of the file with no blank lines or whitespace
before it.

**Native MySQL and Docker behave differently**

Use the host and port that work from the environment running PHP. `localhost` may
use a Unix socket, while `127.0.0.1` uses TCP. Docker MySQL runs on port `3307`
by default, and native MySQL usually runs on `3306`.

**Starting over with Docker**

```bash
docker rm -f showup-mysql
docker volume rm showup-mysql-data
rm -rf ~/.config/showup config/database.php
./scripts/setup.sh
```

## Credits

ShowUp was built by Ahmad and Sultan for a Web Technologies semester project.
