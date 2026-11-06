<?php
/**
 * Edit or cancel an event. Only the host may use this page. Owner: Ahmad.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/event_helpers.php';
require_once __DIR__ . '/../includes/errors.php';

$userId = (int) $_SESSION['user_id'];
$eventId = isset($_GET['event_id']) ? (int) $_GET['event_id'] : 0;

if ($eventId < 1) {
    show_error_page(404, 'Event not found', 'That event does not exist.');
}

$event = event_fetch($conn, $eventId);

if ($event === null) {
    show_error_page(404, 'Event not found', 'That event does not exist.');
}

if ((int) $event['host_id'] !== $userId) {
    show_error_page(403, 'Not allowed', 'Only the host can edit this event.');
}

$pageTitle = 'Edit event';
$activeNav = 'host-dashboard';

if ($event['status'] === 'cancelled') {
    require __DIR__ . '/../includes/header.php';
    ?>
    <main class="page-shell">
      <div class="container">
        <div class="empty-state">
          <h1>This event is cancelled</h1>
          <p>A cancelled event can no longer be edited.</p>
          <p><a class="primary-btn" href="event.php?event_id=<?= (int) $event['event_id'] ?>">View event</a></p>
        </div>
      </div>
    </main>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$categories = fetch_categories($conn);
$categoryIds = array_map('intval', array_column($categories, 'category_id'));
$values = event_values_from_row($event);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        flash_set('error', 'Your session expired. Please try again.');
        redirect('event-edit.php?event_id=' . $eventId);
    }

    $action = (string) ($_POST['action'] ?? 'save');

    if ($action === 'cancel') {
        if (event_cancel($conn, $eventId, $userId)) {
            flash_set('success', 'Event cancelled.');
        } else {
            flash_set('info', 'This event was already cancelled.');
        }

        redirect('event.php?event_id=' . $eventId);
    }

    $values = event_normalise_input($_POST);
    $errors = array_merge(
        event_validate_details($values, $categoryIds),
        event_validate_schedule($values, false),
        event_validate_location($values),
        event_validate_capacity($values)
    );

    // The capacity cannot drop below the number of people already going.
    if (!isset($errors['capacity']) && $values['capacity'] !== ''
        && (int) $values['capacity'] < (int) $event['attendee_count']) {
        $errors['capacity'] = 'Capacity cannot be lower than the '
            . (int) $event['attendee_count'] . ' people already attending.';
    }

    if (!$errors) {
        try {
            event_update($conn, $eventId, $userId, event_prepare_for_save($values));
            flash_set('success', 'Event updated.');
            redirect('event.php?event_id=' . $eventId);
        } catch (mysqli_sql_exception $ex) {
            error_log('Update event failed: ' . $ex->getMessage());
            $errors['form'] = 'Something went wrong while saving the event. Please try again.';
        }
    }
}

$formAction = 'event-edit.php?event_id=' . $eventId;
$submitLabel = 'Save changes';
$cancelUrl = 'event.php?event_id=' . $eventId;

require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell">
  <div class="container">
    <header class="page-header">
      <div>
        <h1>Edit event</h1>
        <p class="page-subtitle"><?= e($values['title']) ?></p>
      </div>
      <a class="secondary-btn" href="event.php?event_id=<?= (int) $event['event_id'] ?>">View event</a>
    </header>

    <?php require __DIR__ . '/../includes/event_form.php'; ?>

    <section class="form-card danger-zone" aria-labelledby="cancel-title">
      <h2 id="cancel-title">Cancel this event</h2>
      <p>Attendees will see the event marked as cancelled. This cannot be undone.</p>
      <form method="post" action="event-edit.php?event_id=<?= (int) $event['event_id'] ?>"
            data-confirm="Cancel this event? Attendees will see it as cancelled.">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel">
        <button type="submit" class="btn-danger">Cancel event</button>
      </form>
    </section>
  </div>
</main>

<script src="assets/js/event-form.js"></script>
<script src="assets/js/confirm-actions.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
