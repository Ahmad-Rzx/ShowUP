<?php
/**
 * Shows the signed-in user's upcoming RSVPs and event history.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/event_helpers.php';

$registrations = user_fetch_registrations($conn, (int) $_SESSION['user_id']);
$upcomingEvents = [];
$pastEvents = [];

foreach ($registrations as $registration) {
    if ((bool) $registration['is_upcoming']) {
        $upcomingEvents[] = $registration;
    } else {
        $pastEvents[] = $registration;
    }
}
$pastEvents = array_reverse($pastEvents);

$sections = [
    ['id' => 'upcoming-events', 'title' => 'Upcoming events', 'events' => $upcomingEvents],
    ['id' => 'past-events', 'title' => 'Past events', 'events' => $pastEvents],
];

$pageTitle = 'My events';
$activeNav = 'my-events';

require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell">
  <div class="container">
    <header class="page-header">
      <div>
        <h1>My events</h1>
        <p class="page-subtitle">Keep track of your RSVPs and event history.</p>
      </div>
      <a class="primary-btn" href="events.php">Browse events</a>
    </header>

    <?php foreach ($sections as $section): ?>
      <section aria-labelledby="<?= e($section['id']) ?>">
        <div class="section-head">
          <h2 class="section-title" id="<?= e($section['id']) ?>"><?= e($section['title']) ?></h2>
        </div>

        <?php if ($section['events'] === []): ?>
          <div class="empty-state">
            <?php if ($section['id'] === 'upcoming-events'): ?>
              <p>You do not have any upcoming RSVPs.</p>
              <p><a class="primary-btn" href="events.php">Find an event</a></p>
            <?php else: ?>
              <p>Your past event history will appear here.</p>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="event-grid event-grid-fill">
            <?php foreach ($section['events'] as $event): ?>
              <?php
              $registrationForStatus = [
                  'status' => $event['status'],
                  'confirmed_at' => $event['confirmed_at'],
                  'checked_in_at' => $event['checked_in_at'],
              ];
              $hasActiveRsvp = in_array($event['status'], ['registered', 'waitlisted'], true);
              $canCancelRsvp = $hasActiveRsvp && $event['checked_in_at'] === null;
              $canChangeRsvp = (bool) $event['is_upcoming'] && $event['event_status'] === 'published';
              ?>
              <article class="event-card">
                <div class="badge-row">
                  <span class="event-badge"><?= e($event['category_name']) ?></span>
                  <span class="mode-badge mode-<?= e($event['event_mode']) ?>">
                    <?= $event['event_mode'] === 'online' ? 'Online' : 'In person' ?>
                  </span>
                  <?php if ($event['event_status'] === 'cancelled'): ?>
                    <span class="mode-badge mode-cancelled">Event cancelled</span>
                  <?php endif; ?>
                </div>

                <h3>
                  <a href="event.php?event_id=<?= (int) $event['event_id'] ?>"><?= e($event['title']) ?></a>
                </h3>
                <p class="field-hint">Hosted by <?= e($event['host_name']) ?></p>
                <p>
                  <span class="status-pill status-<?= e(event_registration_status_class($registrationForStatus)) ?>">
                    <?= e(event_registration_status($registrationForStatus)) ?>
                  </span>
                </p>

                <div class="event-meta">
                  <div class="event-meta-item">
                    <strong>Date</strong>
                    <span><?= e(format_event_when($event['start_datetime'], $event['end_datetime'])) ?></span>
                  </div>
                  <div class="event-meta-item">
                    <strong>Where</strong>
                    <span><?= e($event['location']) ?></span>
                  </div>
                </div>

                <div class="event-actions">
                  <a class="secondary-btn" href="event.php?event_id=<?= (int) $event['event_id'] ?>">View event</a>
                  <?php if ($canChangeRsvp && $canCancelRsvp): ?>
                    <form method="post" action="rsvp.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
                      <input type="hidden" name="action" value="cancel">
                      <input type="hidden" name="return_to" value="my-events">
                      <button type="submit" class="secondary-btn">Cancel RSVP</button>
                    </form>
                  <?php elseif ($canChangeRsvp && !$hasActiveRsvp): ?>
                    <form method="post" action="rsvp.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
                      <input type="hidden" name="action" value="register">
                      <input type="hidden" name="return_to" value="my-events">
                      <button type="submit" class="primary-btn">RSVP again</button>
                    </form>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
