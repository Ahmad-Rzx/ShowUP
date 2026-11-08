<?php
/**
 * Creates, reactivates or cancels the signed-in user's event registration.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

$userId = (int) $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('my-events.php');
}

$eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT);
if ($eventId === false || $eventId === null || $eventId < 1) {
    flash_set('error', 'That event could not be found.');
    redirect('events.php');
}

$eventUrl = 'event.php?event_id=' . $eventId;
$returnTo = ($_POST['return_to'] ?? '') === 'my-events' ? 'my-events.php' : $eventUrl;

if (!csrf_verify($_POST['csrf_token'] ?? null)) {
    flash_set('error', 'Your session expired. Please try again.');
    redirect($returnTo);
}

$actionInput = $_POST['action'] ?? '';
$action = is_string($actionInput) ? $actionInput : '';
if (!in_array($action, ['register', 'cancel'], true)) {
    flash_set('error', 'That registration action is not available.');
    redirect($returnTo);
}

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare(
        'SELECT host_id, status, capacity, start_datetime > NOW() AS is_upcoming
         FROM events WHERE event_id = ? FOR UPDATE'
    );
    $stmt->bind_param('i', $eventId);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($event === null) {
        $conn->rollback();
        flash_set('error', 'That event could not be found.');
        redirect('events.php');
    }

    if ($action === 'register') {
        if ($event['status'] !== 'published' || !(bool) $event['is_upcoming']) {
            $conn->rollback();
            flash_set('error', 'This event is no longer open for RSVPs.');
            redirect($returnTo);
        }

        if ((int) $event['host_id'] === $userId) {
            $conn->rollback();
            flash_set('error', 'You cannot RSVP to an event you are hosting.');
            redirect($returnTo);
        }

        $stmt = $conn->prepare(
            'SELECT status FROM registrations WHERE event_id = ? AND user_id = ? FOR UPDATE'
        );
        $stmt->bind_param('ii', $eventId, $userId);
        $stmt->execute();
        $registration = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($registration !== null && $registration['status'] !== 'cancelled') {
            $conn->rollback();
            flash_set('info', 'You already have an active RSVP for this event.');
            redirect($returnTo);
        }

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS registered_count
             FROM registrations WHERE event_id = ? AND status = 'registered'"
        );
        $stmt->bind_param('i', $eventId);
        $stmt->execute();
        $registeredCount = (int) $stmt->get_result()->fetch_assoc()['registered_count'];
        $stmt->close();

        $hasCapacity = $event['capacity'] === null
            || $registeredCount < (int) $event['capacity'];
        $registrationStatus = $hasCapacity ? 'registered' : 'waitlisted';

        if ($registration === null) {
            $stmt = $conn->prepare(
                'INSERT INTO registrations (event_id, user_id, status) VALUES (?, ?, ?)'
            );
            $stmt->bind_param('iis', $eventId, $userId, $registrationStatus);
        } else {
            $stmt = $conn->prepare(
                'UPDATE registrations
                 SET status = ?, registered_at = NOW(), confirmed_at = NULL,
                     checked_in_at = NULL, cancelled_at = NULL
                 WHERE event_id = ? AND user_id = ?'
            );
            $stmt->bind_param('sii', $registrationStatus, $eventId, $userId);
        }
        $stmt->execute();
        $stmt->close();
        $conn->commit();

        flash_set(
            'success',
            $hasCapacity
                ? 'Your RSVP is confirmed.'
                : 'The event is full, so you have been added to the waitlist.'
        );
        redirect($returnTo);
    }

    if (!(bool) $event['is_upcoming']) {
        $conn->rollback();
        flash_set('error', 'You can no longer cancel an RSVP after the event has started.');
        redirect($returnTo);
    }

    $stmt = $conn->prepare(
        "UPDATE registrations SET status = 'cancelled', cancelled_at = NOW()
         WHERE event_id = ? AND user_id = ? AND status IN ('registered', 'waitlisted')"
    );
    $stmt->bind_param('ii', $eventId, $userId);
    $stmt->execute();
    $changed = $stmt->affected_rows > 0;
    $stmt->close();
    $conn->commit();

    flash_set(
        $changed ? 'success' : 'info',
        $changed ? 'Your RSVP has been cancelled.' : 'You do not have an active RSVP to cancel.'
    );
    redirect($returnTo);
} catch (mysqli_sql_exception $ex) {
    $conn->rollback();
    error_log('RSVP update failed: ' . $ex->getMessage());
    flash_set('error', 'Something went wrong while updating your RSVP. Please try again.');
    redirect($returnTo);
}
