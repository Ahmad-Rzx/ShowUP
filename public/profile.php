<?php
/**
 * Profile page: the user's details, reliability and event history.
 *
 * "Attended" means the registration was checked in. "Hosted" lists every
 * event the user created, including cancelled ones.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/event_helpers.php';
require_once __DIR__ . '/../includes/errors.php';

$userId = (int) $_SESSION['user_id'];
$profile = user_fetch_profile($conn, $userId);

if ($profile === null) {
    show_error_page(404, 'Profile not found', 'Your account could not be loaded.');
}

$reliability = user_reliability_stats($conn, $userId);
$attendedEvents = user_fetch_attended_events($conn, $userId);
$hostedEvents = event_fetch_host_events($conn, $userId);
$memberSince = (new DateTimeImmutable((string) $profile['created_at']))->format('F Y');

$pageTitle = 'My profile';
$activeNav = 'profile';

require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell">
  <div class="container">
    <header class="page-header">
      <div>
        <h1><?= e($profile['full_name']) ?></h1>
        <p class="page-subtitle">
          <?= e($profile['email']) ?>
          <?php if (($profile['city'] ?? '') !== ''): ?>
            · <?= e($profile['city']) ?>
          <?php endif; ?>
          · Member since <?= e($memberSince) ?>
        </p>
      </div>
      <div class="button-row">
        <a class="secondary-btn" href="host-dashboard.php">Host dashboard</a>
        <a class="primary-btn" href="event-create.php">Host an event</a>
      </div>
    </header>

    <section class="summary-bar" aria-label="Your activity summary">
      <div>
        <span class="summary-label">Your reliability</span>
        <?= user_reliability_badge_html($reliability) ?>
      </div>
      <div class="profile-stats">
        <span><strong><?= count($attendedEvents) ?></strong> attended</span>
        <span><strong><?= count($hostedEvents) ?></strong> hosted</span>
        <span>
          <?= (int) $reliability['attended'] ?> of
          <?= (int) $reliability['confirmed'] ?> confirmed events attended
        </span>
      </div>
    </section>

    <?php if (($profile['bio'] ?? '') !== ''): ?>
      <section class="detail-card profile-about" aria-labelledby="about-title">
        <h2 id="about-title">About</h2>
        <p><?= nl2br(e($profile['bio'])) ?></p>
      </section>
    <?php endif; ?>

    <div class="section-head">
      <h2 class="section-title">Events you attended</h2>
    </div>

    <?php if (empty($attendedEvents)): ?>
      <div class="empty-state">
        <p>You have not checked in to any events yet.</p>
        <p><a class="primary-btn" href="home.php">Find an event to join</a></p>
      </div>
    <?php else: ?>
      <div class="table-wrap table-panel">
        <table class="data-table">
          <thead>
            <tr>
              <th scope="col">Event</th>
              <th scope="col">Date</th>
              <th scope="col">Hosted by</th>
              <th scope="col">Checked in</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($attendedEvents as $event): ?>
              <tr>
                <td>
                  <a href="event.php?event_id=<?= (int) $event['event_id'] ?>">
                    <?= e($event['title']) ?>
                  </a>
                  <div class="field-hint"><?= e($event['location']) ?></div>
                </td>
                <td><?= e(format_event_when($event['start_datetime'], $event['end_datetime'])) ?></td>
                <td><?= e($event['host_name']) ?></td>
                <td><?= e(format_event_datetime($event['checked_in_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>

    <div class="section-head">
      <h2 class="section-title">Events you hosted</h2>
      <a class="secondary-btn" href="event-create.php">Host an event</a>
    </div>

    <?php if (empty($hostedEvents)): ?>
      <div class="empty-state">
        <p>You have not hosted any events yet.</p>
        <p><a class="primary-btn" href="event-create.php">Host your first event</a></p>
      </div>
    <?php else: ?>
      <div class="event-grid event-grid-fill">
        <?php foreach ($hostedEvents as $event): ?>
          <article class="event-card">
            <div class="badge-row">
              <span class="event-badge"><?= e($event['category_name']) ?></span>
              <span class="mode-badge mode-<?= e($event['event_mode']) ?>">
                <?= $event['event_mode'] === 'online' ? 'Online' : 'In person' ?>
              </span>
              <?php if ($event['status'] === 'cancelled'): ?>
                <span class="mode-badge mode-cancelled">Cancelled</span>
              <?php endif; ?>
            </div>

            <h3>
              <a href="event.php?event_id=<?= (int) $event['event_id'] ?>"><?= e($event['title']) ?></a>
            </h3>

            <div class="event-meta">
              <div class="event-meta-item">
                <strong>Date</strong>
                <span><?= e(format_event_when($event['start_datetime'], $event['end_datetime'])) ?></span>
              </div>
              <div class="event-meta-item">
                <strong>Where</strong>
                <span><?= e($event['location']) ?></span>
              </div>
              <div class="event-meta-item">
                <strong>Going</strong>
                <span><?= (int) $event['attendee_count'] ?> people</span>
              </div>
            </div>

            <div class="event-actions">
              <a class="secondary-btn" href="event.php?event_id=<?= (int) $event['event_id'] ?>">View</a>
              <?php if ($event['status'] !== 'cancelled'): ?>
                <a class="secondary-btn" href="event-edit.php?event_id=<?= (int) $event['event_id'] ?>">Edit</a>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
