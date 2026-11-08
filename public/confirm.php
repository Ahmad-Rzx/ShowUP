<?php
/**
 * Confirms the signed-in user's active RSVP before the event starts.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('my-events.php');
}

$eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT);
if ($eventId === false || $eventId === null || $eventId < 1) {
    flash_set('error', 'That event could not be found.');
    redirect('my-events.php');
}

$eventUrl = 'event.php?event_id=' . $eventId;
if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect($eventUrl);
}

$userId = (int) $_SESSION['user_id'];

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare(
        'SELECT status, start_datetime > NOW() AS is_upcoming
         FROM events WHERE event_id = ? FOR UPDATE'
    );
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($event === null || $event['status'] !== 'published' || !(bool) $event['is_upcoming']) {
        $conn->rollback();
        flash_set('error', 'This event is no longer open for confirmation.');
        redirect($eventUrl);
    }

    $stmt = $conn->prepare(
        "UPDATE registrations
         SET confirmed_at = NOW()
         WHERE event_id = ? AND user_id = ? AND status = 'registered'
           AND confirmed_at IS NULL"
    );
    $stmt->bind_param('ii', $eventId, $userId);
    $stmt->execute();
    $changed = $stmt->affected_rows > 0;
    $stmt->close();
    $conn->commit();

    flash_set(
        $changed ? 'success' : 'info',
        $changed
            ? 'Your attendance is confirmed. Event details and check-in will be available here.'
            : 'No unconfirmed active RSVP was found for this event.'
    );
    redirect($eventUrl);
} catch (mysqli_sql_exception $ex) {
    $conn->rollback();
    error_log('Event confirmation failed: ' . $ex->getMessage());
    flash_set('error', 'Something went wrong while confirming your RSVP. Please try again.');
    redirect($eventUrl);
}
