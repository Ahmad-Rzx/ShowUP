<?php
/**
 * Home page: a small dashboard for the logged-in user.
 *
 * Shows upcoming events the user can still join, plus shortcuts to their
 * profile and host dashboard. Registration itself happens on event.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/event_helpers.php';

$userId = (int) $_SESSION['user_id'];
$userName = (string) ($_SESSION['user_name'] ?? 'Friend');
$reliability = user_reliability_stats($conn, $userId);
$upcomingEvents = event_fetch_upcoming_for_user($conn, $userId, 6);
$browsePageExists = is_file(__DIR__ . '/events.php');

$pageTitle = 'Home';
$activeNav = 'home';

require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell">
  <div class="container">
    <header class="page-header">
      <div>
        <h1>Hello, <?= e($userName) ?></h1>
        <p class="page-subtitle">Find your next event and keep track of who is coming.</p>
      </div>
      <div class="button-row">
        <a class="secondary-btn" href="profile.php">My profile</a>
        <a class="secondary-btn" href="host-dashboard.php">Host dashboard</a>
        <a class="primary-btn" href="event-create.php">Host an event</a>
      </div>
    </header>

    <section class="summary-bar" aria-label="Your reliability summary">
      <div>
        <span class="summary-label">Your reliability</span>
        <?= user_reliability_badge_html($reliability) ?>
      </div>
      <div class="field-hint">
        <?= (int) $reliability['attended'] ?> attended out of
        <?= (int) $reliability['confirmed'] ?> confirmed events.
      </div>
    </section>

    <div class="section-head">
      <h2 class="section-title">Upcoming events you can join</h2>
      <?php if ($browsePageExists): ?>
        <a class="secondary-btn" href="events.php">Browse all events</a>
      <?php endif; ?>
    </div>

    <?php if (empty($upcomingEvents)): ?>
      <div class="empty-state">
        <p>There are no upcoming events to join right now.</p>
        <p><a class="primary-btn" href="event-create.php">Host your own event</a></p>
      </div>
    <?php else: ?>
      <div class="event-grid">
        <?php foreach ($upcomingEvents as $event): ?>
          <article class="event-card">
            <div class="badge-row">
              <span class="event-badge"><?= e($event['category_name']) ?></span>
              <span class="mode-badge mode-<?= e($event['event_mode']) ?>">
                <?= $event['event_mode'] === 'online' ? 'Online' : 'In person' ?>
              </span>
            </div>

            <h3>
              <a href="event.php?event_id=<?= (int) $event['event_id'] ?>"><?= e($event['title']) ?></a>
            </h3>
            <p class="field-hint">Hosted by <?= e($event['host_name']) ?></p>
            <p><?= e(text_excerpt((string) $event['description'])) ?></p>

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
              <a class="primary-btn" href="event.php?event_id=<?= (int) $event['event_id'] ?>">View event</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
