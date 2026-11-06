<?php
/**
 * Handles the attendee check-in form. Owner: Ahmad.
 *
 * A check-in is only accepted when:
 *   - the user has a registration that is not cancelled,
 *   - the registration is confirmed,
 *   - the event is published and inside the check-in window,
 *   - the entered code matches the event's code.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/event_helpers.php';

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('home.php');
}

$eventId = isset($_POST['event_id']) ? (int) $_POST['event_id'] : 0;

if ($eventId < 1) {
    flash_set('error', 'That event could not be found.');
    redirect('home.php');
}

$eventUrl = 'event.php?event_id=' . $eventId;

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect($eventUrl);
}

$event = event_fetch($conn, $eventId);

if ($event === null || $event['status'] === 'cancelled') {
    flash_set('error', 'That event is not available for check-in.');
    redirect('home.php');
}

$registration = event_fetch_registration($conn, $eventId, $userId);

if ($registration === null || $registration['status'] !== 'registered') {
    flash_set('error', 'You do not have an active registration for this event.');
    redirect($eventUrl);
}

if ($registration['checked_in_at'] !== null) {
    flash_set('info', 'You are already checked in.');
    redirect($eventUrl);
}

if ($registration['confirmed_at'] === null) {
    flash_set('error', 'Confirm your RSVP before checking in.');
    redirect($eventUrl);
}

if (!(bool) $event['checkin_open']) {
    flash_set('error', 'Check-in is not open for this event right now.');
    redirect($eventUrl);
}

$submittedCode = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['checkin_code'] ?? '')));

if ($submittedCode === '' || !hash_equals((string) $event['checkin_code'], $submittedCode)) {
    flash_set('error', 'That check-in code is not correct.');
    redirect($eventUrl);
}

$registrationId = (int) $registration['registration_id'];
$stmt = $conn->prepare(
    'UPDATE registrations SET checked_in_at = NOW()
     WHERE registration_id = ? AND checked_in_at IS NULL'
);
$stmt->bind_param('i', $registrationId);
$stmt->execute();
$changed = $stmt->affected_rows > 0;
$stmt->close();

if ($changed) {
    flash_set('success', 'Checked in. Thanks for showing up.');
} else {
    flash_set('info', 'You are already checked in.');
}

redirect($eventUrl);
