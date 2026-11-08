<?php
/**
 * Stores one review from a user who checked in to the event.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/event_helpers.php';

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

$rating = filter_var($_POST['rating'] ?? null, FILTER_VALIDATE_INT);
$commentInput = $_POST['comment'] ?? '';
$comment = is_string($commentInput) ? trim($commentInput) : '';
$errors = [];

if ($rating === false || $rating === null || $rating < 1 || $rating > 5) {
    $errors[] = 'Choose a rating from 1 to 5.';
}
if (char_length($comment) > 1000) {
    $errors[] = 'Your comment must be 1000 characters or fewer.';
}
if ($errors !== []) {
    flash_set('error', implode(' ', $errors));
    redirect($eventUrl);
}

$userId = (int) $_SESSION['user_id'];
$registration = event_fetch_registration($conn, $eventId, $userId);
if ($registration === null || $registration['checked_in_at'] === null) {
    flash_set('error', 'Only attendees who have checked in can review this event.');
    redirect($eventUrl);
}

$existingReview = event_fetch_review($conn, $eventId, $userId);
if ($existingReview !== null) {
    flash_set('info', 'You have already reviewed this event.');
    redirect($eventUrl);
}

$nullableComment = $comment !== '' ? $comment : null;

try {
    $stmt = $conn->prepare(
        'INSERT INTO event_reviews (event_id, user_id, rating, comment)
         VALUES (?, ?, ?, ?)'
    );
    $stmt->bind_param('iiis', $eventId, $userId, $rating, $nullableComment);
    $stmt->execute();
    $stmt->close();

    flash_set('success', 'Thanks for reviewing this event.');
} catch (mysqli_sql_exception $ex) {
    if ($ex->getCode() === 1062) {
        flash_set('info', 'You have already reviewed this event.');
    } else {
        error_log('Event review failed: ' . $ex->getMessage());
        flash_set('error', 'Something went wrong while saving your review. Please try again.');
    }
}

redirect($eventUrl);
