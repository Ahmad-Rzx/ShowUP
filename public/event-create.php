<?php
/**
 * Host an event: shows the form (GET) and saves the event (POST). Owner: Ahmad.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/event_helpers.php';

$userId = (int) $_SESSION['user_id'];
$categories = fetch_categories($conn);
$categoryIds = array_map('intval', array_column($categories, 'category_id'));
$values = event_default_values();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $values = event_normalise_input($_POST);

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please try again.';
    } else {
        $errors = array_merge(
            event_validate_details($values, $categoryIds),
            event_validate_schedule($values, true),
            event_validate_location($values),
            event_validate_capacity($values)
        );

        if (!$errors) {
            try {
                $eventId = event_insert($conn, $userId, event_prepare_for_save($values));
                flash_set('success', 'Event created. You can edit or cancel it from your host dashboard.');
                redirect('event.php?event_id=' . $eventId);
            } catch (mysqli_sql_exception $ex) {
                error_log('Create event failed: ' . $ex->getMessage());
                $errors['form'] = 'Something went wrong while saving the event. Please try again.';
            }
        }
    }
}

$pageTitle = 'Host an event';
$activeNav = 'host-create';
$formAction = 'event-create.php';
$submitLabel = 'Publish event';
$cancelUrl = 'home.php';

require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell">
  <div class="container">
    <header class="page-header">
      <div>
        <h1>Host an event</h1>
        <p class="page-subtitle">Set the details now. You can edit or cancel it later.</p>
      </div>
    </header>

    <?php require __DIR__ . '/../includes/event_form.php'; ?>
  </div>
</main>

<script src="assets/js/event-form.js"></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
