<?php
/**
 * Event queries, validation and display helpers. Owner: Ahmad.
 *
 * Every page that works with events includes this after helpers.php:
 *
 *     require_once __DIR__ . '/../includes/event_helpers.php';
 */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/** Returns a random six-character code that is easy to read out loud. */
function event_generate_checkin_code(): string
{
    // No 0/O or 1/I, because those are easy to confuse when read from a screen.
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';
    $lastIndex = strlen($alphabet) - 1;

    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, $lastIndex)];
    }

    return $code;
}

/** Short location text for cards: "Venue, City" for physical events, "Online" otherwise. */
function event_location_summary(string $mode, string $venueName, string $city): string
{
    if ($mode === 'online') {
        return 'Online';
    }

    return $city !== '' ? $venueName . ', ' . $city : $venueName;
}

/** Converts a database datetime into the value a datetime-local input expects. */
function event_datetime_input_value(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }

    return str_replace(' ', 'T', substr($datetime, 0, 16));
}

/** Formats a database datetime for people, for example "Tue, 10 Nov 2026, 6:00 PM". */
function format_event_datetime(string $datetime): string
{
    return (new DateTimeImmutable($datetime))->format('D, j M Y, g:i A');
}

/** Formats the start and end of an event, showing the date once when it is the same day. */
function format_event_when(string $start, string $end): string
{
    $startDate = new DateTimeImmutable($start);
    $endDate = new DateTimeImmutable($end);

    if ($startDate->format('Y-m-d') === $endDate->format('Y-m-d')) {
        return $startDate->format('D, j M Y, g:i A') . ' - ' . $endDate->format('g:i A');
    }

    return $startDate->format('D, j M Y, g:i A') . ' - ' . $endDate->format('D, j M Y, g:i A');
}

/** Returns the start and end of the check-in window as DateTimeImmutable objects. */
function event_checkin_window(array $event): array
{
    $opens = (new DateTimeImmutable((string) $event['start_datetime']))->modify('-30 minutes');
    $closes = new DateTimeImmutable((string) $event['end_datetime']);

    return ['opens' => $opens, 'closes' => $closes];
}

/** All categories, used by the create and edit forms. */
function fetch_categories(mysqli $conn): array
{
    $result = $conn->query('SELECT category_id, name FROM categories ORDER BY name');

    return $result->fetch_all(MYSQLI_ASSOC);
}

/** Loads one event with its category, host and attendance counts, or null when missing. */
function event_fetch(mysqli $conn, int $eventId): ?array
{
    $sql = <<<SQL
        SELECT e.*,
               c.name AS category_name,
               u.full_name AS host_name,
               (NOW() BETWEEN e.start_datetime - INTERVAL 30 MINUTE AND e.end_datetime) AS checkin_open,
               (e.start_datetime > NOW()) AS registration_open,
               (SELECT COUNT(*) FROM registrations r
                 WHERE r.event_id = e.event_id AND r.status = 'registered') AS attendee_count,
               (SELECT COUNT(*) FROM registrations r
                 WHERE r.event_id = e.event_id AND r.status = 'registered'
                   AND r.confirmed_at IS NOT NULL) AS confirmed_count
        FROM events e
        JOIN categories c ON c.category_id = e.category_id
        JOIN users u ON u.user_id = e.host_id
        WHERE e.event_id = ?
        LIMIT 1
        SQL;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $event ?: null;
}

/** Loads this user's registration for an event, or null when they have not signed up. */
function event_fetch_registration(mysqli $conn, int $eventId, int $userId): ?array
{
    $stmt = $conn->prepare(
        'SELECT * FROM registrations WHERE event_id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $eventId, $userId);
    $stmt->execute();
    $registration = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $registration ?: null;
}

/** Loads this user's review for an event, or null when no review has been submitted. */
function event_fetch_review(mysqli $conn, int $eventId, int $userId): ?array
{
    $stmt = $conn->prepare(
        'SELECT rating, comment, created_at
         FROM event_reviews WHERE event_id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $eventId, $userId);
    $stmt->execute();
    $review = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $review ?: null;
}

/** All events this user registered for, including waitlisted and cancelled registrations. */
function user_fetch_registrations(mysqli $conn, int $userId): array
{
    $sql = <<<SQL
        SELECT r.registration_id, r.status,
               r.registered_at, r.confirmed_at, r.checked_in_at, r.cancelled_at,
               e.event_id, e.title, e.description, e.event_mode, e.location,
               e.start_datetime, e.end_datetime, e.capacity, e.status AS event_status,
               c.name AS category_name, u.full_name AS host_name,
               (e.start_datetime > NOW()) AS is_upcoming
        FROM registrations r
        JOIN events e ON e.event_id = r.event_id
        JOIN categories c ON c.category_id = e.category_id
        JOIN users u ON u.user_id = e.host_id
        WHERE r.user_id = ?
        ORDER BY e.start_datetime ASC
        SQL;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $registrations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $registrations;
}

/**
 * Moves the oldest waitlisted registrations up while the event has free seats.
 * Returns the number of attendees promoted. The caller should hold the event
 * row lock (for example inside the RSVP transaction) to avoid overbooking.
 */
function event_promote_waitlisted(mysqli $conn, int $eventId): int
{
    $stmt = $conn->prepare('SELECT capacity FROM events WHERE event_id = ? LIMIT 1');
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($event === null || $event['capacity'] === null) {
        return 0;
    }

    $capacity = (int) $event['capacity'];
    $promoted = 0;

    while (true) {
        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS registered_count
             FROM registrations WHERE event_id = ? AND status = 'registered'"
        );
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $registeredCount = (int) $stmt->get_result()->fetch_assoc()['registered_count'];
        $stmt->close();

        if ($registeredCount >= $capacity) {
            break;
        }

        $stmt = $conn->prepare(
            "SELECT registration_id FROM registrations
             WHERE event_id = ? AND status = 'waitlisted'
             ORDER BY registered_at ASC, registration_id ASC
             LIMIT 1 FOR UPDATE"
        );
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $waitlisted = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($waitlisted === null) {
            break;
        }

        $registrationId = (int) $waitlisted['registration_id'];
        $stmt = $conn->prepare(
            "UPDATE registrations
             SET status = 'registered', confirmed_at = NULL,
                 checked_in_at = NULL, cancelled_at = NULL
             WHERE registration_id = ? AND status = 'waitlisted'"
        );
        $stmt->bind_param('i', $registrationId);
        $stmt->execute();
        $changed = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$changed) {
            break;
        }

        $promoted++;
    }

    return $promoted;
}

/** Everyone who signed up for an event, with their reliability counters. */
function event_fetch_attendees(mysqli $conn, int $eventId): array
{
    $sql = <<<SQL
        SELECT r.registration_id, r.status, r.registered_at, r.confirmed_at,
               r.checked_in_at, r.cancelled_at,
               u.user_id, u.full_name, u.email,
               COALESCE(ur.attended, 0)  AS reliability_attended,
               COALESCE(ur.confirmed, 0) AS reliability_confirmed
        FROM registrations r
        JOIN users u ON u.user_id = r.user_id
        LEFT JOIN user_reliability ur ON ur.user_id = r.user_id
        WHERE r.event_id = ?
        ORDER BY r.registered_at ASC
        SQL;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $attendees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $attendees;
}

/** Every event hosted by this user, newest first, with attendance counts. */
function event_fetch_host_events(mysqli $conn, int $hostId): array
{
    $sql = <<<SQL
        SELECT e.*, c.name AS category_name,
               (SELECT COUNT(*) FROM registrations r
                 WHERE r.event_id = e.event_id AND r.status = 'registered') AS attendee_count,
               (SELECT COUNT(*) FROM registrations r
                 WHERE r.event_id = e.event_id AND r.status = 'registered'
                   AND r.confirmed_at IS NOT NULL) AS confirmed_count,
               (SELECT COUNT(*) FROM registrations r
                 WHERE r.event_id = e.event_id
                   AND r.checked_in_at IS NOT NULL) AS checked_in_count
        FROM events e
        JOIN categories c ON c.category_id = e.category_id
        WHERE e.host_id = ?
        ORDER BY e.start_datetime DESC
        SQL;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $hostId);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $events;
}

/**
 * Upcoming published events this user can still join.
 * Excludes events they host and events they already registered or waitlisted for.
 */
function event_fetch_upcoming_for_user(mysqli $conn, int $userId, int $limit = 6): array
{
    $sql = <<<SQL
        SELECT e.*, c.name AS category_name, u.full_name AS host_name,
               (SELECT COUNT(*) FROM registrations r
                 WHERE r.event_id = e.event_id AND r.status = 'registered') AS attendee_count
        FROM events e
        JOIN categories c ON c.category_id = e.category_id
        JOIN users u ON u.user_id = e.host_id
        WHERE e.status = 'published'
          AND e.start_datetime > NOW()
          AND e.host_id <> ?
          AND NOT EXISTS (
              SELECT 1 FROM registrations r
              WHERE r.event_id = e.event_id
                AND r.user_id = ?
                AND r.status IN ('registered', 'waitlisted')
          )
        ORDER BY e.start_datetime ASC
        LIMIT ?
        SQL;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iii', $userId, $userId, $limit);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $events;
}

/** Loads the public account details shown on a profile page. */
function user_fetch_profile(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare(
        'SELECT user_id, full_name, email, city, bio, created_at, last_login_at
         FROM users WHERE user_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $profile ?: null;
}

/** Events this user actually attended (checked in), most recent first. */
function user_fetch_attended_events(mysqli $conn, int $userId): array
{
    $sql = <<<SQL
        SELECT e.*, c.name AS category_name, u.full_name AS host_name,
               r.checked_in_at
        FROM registrations r
        JOIN events e ON e.event_id = r.event_id
        JOIN categories c ON c.category_id = e.category_id
        JOIN users u ON u.user_id = e.host_id
        WHERE r.user_id = ?
          AND r.status = 'registered'
          AND r.checked_in_at IS NOT NULL
        ORDER BY e.start_datetime DESC
        SQL;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $events;
}

/** Empty form values, used when the host opens the create page. */
function event_default_values(): array
{
    return [
        'title' => '',
        'description' => '',
        'category_id' => 0,
        'event_mode' => 'physical',
        'venue_name' => '',
        'address' => '',
        'city' => '',
        'online_link' => '',
        'start_datetime' => '',
        'end_datetime' => '',
        'capacity' => '',
    ];
}

/** Turns a database row into the values the shared form expects. */
function event_values_from_row(array $event): array
{
    return [
        'title' => (string) $event['title'],
        'description' => (string) $event['description'],
        'category_id' => (int) $event['category_id'],
        'event_mode' => (string) $event['event_mode'],
        'venue_name' => (string) ($event['venue_name'] ?? ''),
        'address' => (string) ($event['address'] ?? ''),
        'city' => (string) ($event['city'] ?? ''),
        'online_link' => (string) ($event['online_link'] ?? ''),
        'start_datetime' => event_datetime_input_value((string) $event['start_datetime']),
        'end_datetime' => event_datetime_input_value((string) $event['end_datetime']),
        'capacity' => $event['capacity'] === null ? '' : (string) $event['capacity'],
    ];
}

/** Reads and trims the submitted event form. */
function event_normalise_input(array $input): array
{
    return [
        'title' => trim((string) ($input['title'] ?? '')),
        'description' => trim((string) ($input['description'] ?? '')),
        'category_id' => (int) ($input['category_id'] ?? 0),
        'event_mode' => (string) ($input['event_mode'] ?? 'physical'),
        'venue_name' => trim((string) ($input['venue_name'] ?? '')),
        'address' => trim((string) ($input['address'] ?? '')),
        'city' => trim((string) ($input['city'] ?? '')),
        'online_link' => trim((string) ($input['online_link'] ?? '')),
        'start_datetime' => trim((string) ($input['start_datetime'] ?? '')),
        'end_datetime' => trim((string) ($input['end_datetime'] ?? '')),
        'capacity' => trim((string) ($input['capacity'] ?? '')),
    ];
}

/** Parses a datetime-local value, returning null when it is not a real date. */
function event_parse_datetime(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);

    if ($date === false || $date->format('Y-m-d\TH:i') !== $value) {
        return null;
    }

    return $date;
}

/** Checks the title, description, category and mode. */
function event_validate_details(array $values, array $categoryIds): array
{
    $errors = [];
    $titleLength = char_length($values['title']);
    $descriptionLength = char_length($values['description']);

    if ($titleLength < 3 || $titleLength > 150) {
        $errors['title'] = 'Use a title between 3 and 150 characters.';
    }

    if ($descriptionLength < 10 || $descriptionLength > 5000) {
        $errors['description'] = 'Use a description between 10 and 5000 characters.';
    }

    if (!in_array((int) $values['category_id'], $categoryIds, true)) {
        $errors['category_id'] = 'Choose a category.';
    }

    if (!in_array($values['event_mode'], ['physical', 'online'], true)) {
        $errors['event_mode'] = 'Choose in person or online.';
    }

    return $errors;
}

/** Checks the start and end times. New events must start in the future. */
function event_validate_schedule(array $values, bool $mustBeFuture): array
{
    $errors = [];
    $start = event_parse_datetime($values['start_datetime']);
    $end = event_parse_datetime($values['end_datetime']);

    if ($start === null) {
        $errors['start_datetime'] = 'Enter a valid start date and time.';
    }

    if ($end === null) {
        $errors['end_datetime'] = 'Enter a valid end date and time.';
    }

    if ($start !== null && $end !== null && $end <= $start) {
        $errors['end_datetime'] = 'The end time must be after the start time.';
    }

    if ($mustBeFuture && $start !== null && $start <= new DateTimeImmutable('now')) {
        $errors['start_datetime'] = 'The start time must be in the future.';
    }

    return $errors;
}

/** Checks the fields that change with the event mode. */
function event_validate_location(array $values): array
{
    $errors = [];

    if ($values['event_mode'] === 'physical') {
        $venueLength = char_length($values['venue_name']);
        $addressLength = char_length($values['address']);

        if ($venueLength < 2 || $venueLength > 150) {
            $errors['venue_name'] = 'Enter a venue name (2 to 150 characters).';
        }

        if ($addressLength < 5 || $addressLength > 255) {
            $errors['address'] = 'Enter an address (5 to 255 characters).';
        }

        if (char_length($values['city']) > 80) {
            $errors['city'] = 'City must be 80 characters or fewer.';
        }

        return $errors;
    }

    $linkLength = char_length($values['online_link']);
    $scheme = strtolower((string) parse_url($values['online_link'], PHP_URL_SCHEME));

    if ($linkLength < 10 || $linkLength > 500) {
        $errors['online_link'] = 'Enter a meeting link (10 to 500 characters).';
    } elseif (!filter_var($values['online_link'], FILTER_VALIDATE_URL)
        || !in_array($scheme, ['http', 'https'], true)) {
        $errors['online_link'] = 'Enter a full link starting with http:// or https://.';
    }

    return $errors;
}

/** Checks the optional seat limit. An empty value means unlimited. */
function event_validate_capacity(array $values): array
{
    if ($values['capacity'] === '') {
        return [];
    }

    if (!preg_match('/^\d+$/', $values['capacity'])
        || (int) $values['capacity'] < 1
        || (int) $values['capacity'] > 100000) {
        return ['capacity' => 'Capacity must be a whole number between 1 and 100000.'];
    }

    return [];
}

/** Builds the exact values saved to the database from validated form input. */
function event_prepare_for_save(array $values): array
{
    $isPhysical = $values['event_mode'] === 'physical';

    return [
        'category_id' => (int) $values['category_id'],
        'title' => $values['title'],
        'description' => $values['description'],
        'event_mode' => $values['event_mode'],
        'venue_name' => $isPhysical ? $values['venue_name'] : null,
        'address' => $isPhysical ? $values['address'] : null,
        'online_link' => $isPhysical ? null : $values['online_link'],
        'location' => event_location_summary($values['event_mode'], $values['venue_name'], $values['city']),
        'city' => $isPhysical && $values['city'] !== '' ? $values['city'] : null,
        'start_datetime' => str_replace('T', ' ', $values['start_datetime']) . ':00',
        'end_datetime' => str_replace('T', ' ', $values['end_datetime']) . ':00',
        'capacity' => $values['capacity'] === '' ? null : (int) $values['capacity'],
    ];
}

/** Inserts an event and returns its new id. */
function event_insert(mysqli $conn, int $hostId, array $data): int
{
    $sql = 'INSERT INTO events
              (host_id, category_id, title, description, event_mode, venue_name,
               address, online_link, location, city, start_datetime, end_datetime,
               capacity, checkin_code)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $categoryId = $data['category_id'];
    $title = $data['title'];
    $description = $data['description'];
    $eventMode = $data['event_mode'];
    $venueName = $data['venue_name'];
    $address = $data['address'];
    $onlineLink = $data['online_link'];
    $location = $data['location'];
    $city = $data['city'];
    $start = $data['start_datetime'];
    $end = $data['end_datetime'];
    $capacity = $data['capacity'];
    $checkinCode = event_generate_checkin_code();

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssssssssssssss',
        $hostId,
        $categoryId,
        $title,
        $description,
        $eventMode,
        $venueName,
        $address,
        $onlineLink,
        $location,
        $city,
        $start,
        $end,
        $capacity,
        $checkinCode
    );
    $stmt->execute();
    $eventId = (int) $conn->insert_id;
    $stmt->close();

    return $eventId;
}

/** Saves changes to an existing event. The host id in the WHERE clause is the ownership check. */
function event_update(mysqli $conn, int $eventId, int $hostId, array $data): void
{
    $sql = 'UPDATE events
            SET category_id = ?, title = ?, description = ?, event_mode = ?,
                venue_name = ?, address = ?, online_link = ?, location = ?, city = ?,
                start_datetime = ?, end_datetime = ?, capacity = ?
            WHERE event_id = ? AND host_id = ?';

    $categoryId = $data['category_id'];
    $title = $data['title'];
    $description = $data['description'];
    $eventMode = $data['event_mode'];
    $venueName = $data['venue_name'];
    $address = $data['address'];
    $onlineLink = $data['online_link'];
    $location = $data['location'];
    $city = $data['city'];
    $start = $data['start_datetime'];
    $end = $data['end_datetime'];
    $capacity = $data['capacity'];

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssssssssssssss',
        $categoryId,
        $title,
        $description,
        $eventMode,
        $venueName,
        $address,
        $onlineLink,
        $location,
        $city,
        $start,
        $end,
        $capacity,
        $eventId,
        $hostId
    );
    $stmt->execute();
    $stmt->close();
}

/** Marks an event as cancelled. Returns false when it was already cancelled. */
function event_cancel(mysqli $conn, int $eventId, int $hostId): bool
{
    $stmt = $conn->prepare(
        "UPDATE events SET status = 'cancelled'
         WHERE event_id = ? AND host_id = ? AND status = 'published'"
    );
    $stmt->bind_param('ii', $eventId, $hostId);
    $stmt->execute();
    $changed = $stmt->affected_rows > 0;
    $stmt->close();

    return $changed;
}

/** Human-readable registration state: Registered, Confirmed, Checked in or Cancelled. */
function event_registration_status(array $registration): string
{
    if ($registration['status'] === 'cancelled') {
        return 'Cancelled';
    }

    if ($registration['checked_in_at'] !== null) {
        return 'Checked in';
    }

    if ($registration['confirmed_at'] !== null) {
        return 'Confirmed';
    }

    if ($registration['status'] === 'waitlisted') {
        return 'Waitlisted';
    }

    return 'Registered';
}

/** CSS-safe version of the registration state. */
function event_registration_status_class(array $registration): string
{
    $classes = [
        'Registered' => 'registered',
        'Waitlisted' => 'waitlisted',
        'Confirmed' => 'confirmed',
        'Checked in' => 'checked-in',
        'Cancelled' => 'cancelled',
    ];

    return $classes[event_registration_status($registration)] ?? 'registered';
}

/** Reads this user's reliability counters from the view. Missing rows mean zero events. */
function user_reliability_stats(mysqli $conn, int $userId): array
{
    $stmt = $conn->prepare(
        'SELECT attended, confirmed, no_shows, late_cancels
         FROM user_reliability WHERE user_id = ? LIMIT 1'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['attended' => 0, 'confirmed' => 0, 'no_shows' => 0, 'late_cancels' => 0];
    }

    return [
        'attended' => (int) $row['attended'],
        'confirmed' => (int) $row['confirmed'],
        'no_shows' => (int) $row['no_shows'],
        'late_cancels' => (int) $row['late_cancels'],
    ];
}

/**
 * Turns reliability counters into a label.
 * Fewer than three confirmed events is always "New", so one bad event does not mark someone.
 */
function user_reliability_badge(array $stats): array
{
    $confirmed = (int) $stats['confirmed'];
    $attended = (int) $stats['attended'];

    if ($confirmed < 3) {
        return [
            'label' => 'New',
            'class' => 'new',
            'percent' => null,
            'detail' => 'Fewer than three confirmed events',
        ];
    }

    $percent = (int) round($attended / $confirmed * 100);

    if ($percent >= 90) {
        $label = 'Highly reliable';
        $class = 'good';
    } elseif ($percent >= 70) {
        $label = 'Reliable';
        $class = 'ok';
    } elseif ($percent >= 40) {
        $label = 'Sometimes reliable';
        $class = 'warn';
    } else {
        $label = 'Unreliable';
        $class = 'bad';
    }

    return [
        'label' => $label,
        'class' => $class,
        'percent' => $percent,
        'detail' => 'Attended ' . $attended . ' of ' . $confirmed . ' confirmed events',
    ];
}

/** Ready-to-print reliability badge. The text is escaped; the classes are fixed values. */
function user_reliability_badge_html(array $stats): string
{
    $badge = user_reliability_badge($stats);
    $text = $badge['label'];

    if ($badge['percent'] !== null) {
        $text .= ' (' . $badge['percent'] . '%)';
    }

    return '<span class="reliability reliability-' . e($badge['class'])
        . '" title="' . e($badge['detail']) . '">' . e($text) . '</span>';
}
