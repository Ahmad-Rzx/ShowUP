<?php
/**
 * Host dashboard: the host's events, check-in codes and attendee lists. Owner: Ahmad.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/event_helpers.php';

$userId = (int) $_SESSION['user_id'];
$events = event_fetch_host_events($conn, $userId);
$hostReliability = user_reliability_stats($conn, $userId);

$pageTitle = 'Host dashboard';
$activeNav = 'host-dashboard';

require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell">
  <div class="container">
    <header class="page-header">
      <div>
        <h1>Host dashboard</h1>
        <p class="page-subtitle">Your events, check-in codes and attendee lists.</p>
      </div>
      <a class="primary-btn" href="event-create.php">Host an event</a>
    </header>

    <section class="summary-bar" aria-label="Your reliability summary">
      <div>
        <span class="summary-label">Your reliability</span>
        <?= user_reliability_badge_html($hostReliability) ?>
      </div>
      <div class="field-hint">
        <?= (int) $hostReliability['attended'] ?> attended out of
        <?= (int) $hostReliability['confirmed'] ?> confirmed events.
        New hosts need three confirmed events before a label is shown.
      </div>
    </section>

    <?php if (empty($events)): ?>
      <div class="empty-state">
        <h2>No events yet</h2>
        <p>When you host your first event, it will appear here with its attendee list.</p>
        <p><a class="primary-btn" href="event-create.php">Host your first event</a></p>
      </div>
    <?php else: ?>
      <?php foreach ($events as $event): ?>
        <?php $attendees = event_fetch_attendees($conn, (int) $event['event_id']); ?>
        <article class="host-event">
          <div class="host-event-head">
            <div>
              <div class="badge-row">
                <span class="event-badge"><?= e($event['category_name']) ?></span>
                <span class="mode-badge mode-<?= e($event['event_mode']) ?>">
                  <?= $event['event_mode'] === 'online' ? 'Online' : 'In person' ?>
                </span>
                <?php if ($event['status'] === 'cancelled'): ?>
                  <span class="mode-badge mode-cancelled">Cancelled</span>
                <?php endif; ?>
              </div>

              <h2>
                <a href="event.php?event_id=<?= (int) $event['event_id'] ?>"><?= e($event['title']) ?></a>
              </h2>
              <p class="field-hint">
                <?= e(format_event_when($event['start_datetime'], $event['end_datetime'])) ?>
                - <?= e($event['location']) ?>
              </p>
            </div>

            <div class="button-row">
              <a class="secondary-btn" href="event.php?event_id=<?= (int) $event['event_id'] ?>">View</a>
              <?php if ($event['status'] !== 'cancelled'): ?>
                <a class="primary-btn" href="event-edit.php?event_id=<?= (int) $event['event_id'] ?>">Edit</a>
              <?php endif; ?>
            </div>
          </div>

          <div class="host-event-body">
            <div class="host-event-stats">
              <span><strong><?= (int) $event['attendee_count'] ?></strong> RSVPs</span>
              <span><strong><?= (int) $event['confirmed_count'] ?></strong> confirmed</span>
              <span><strong><?= (int) $event['checked_in_count'] ?></strong> checked in</span>
              <span class="checkin-code-wrap">
                Check-in code:
                <span class="checkin-code"><?= e($event['checkin_code']) ?></span>
              </span>
            </div>

            <?php if (empty($attendees)): ?>
              <p class="empty-note">No one has RSVP'd to this event yet.</p>
            <?php else: ?>
              <div class="table-wrap">
                <table class="data-table">
                  <thead>
                    <tr>
                      <th scope="col">Attendee</th>
                      <th scope="col">Status</th>
                      <th scope="col">Reliability</th>
                      <th scope="col">RSVP</th>
                      <th scope="col">Confirmed</th>
                      <th scope="col">Checked in</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($attendees as $attendee): ?>
                      <?php
                      $stats = [
                          'attended' => (int) $attendee['reliability_attended'],
                          'confirmed' => (int) $attendee['reliability_confirmed'],
                      ];
                      ?>
                      <tr>
                        <td>
                          <strong><?= e($attendee['full_name']) ?></strong><br>
                          <span class="field-hint"><?= e($attendee['email']) ?></span>
                        </td>
                        <td>
                          <span class="status-pill status-<?= e(event_registration_status_class($attendee)) ?>">
                            <?= e(event_registration_status($attendee)) ?>
                          </span>
                        </td>
                        <td><?= user_reliability_badge_html($stats) ?></td>
                        <td><?= e(format_event_datetime($attendee['registered_at'])) ?></td>
                        <td>
                          <?= $attendee['confirmed_at'] === null
                              ? '-'
                              : e(format_event_datetime($attendee['confirmed_at'])) ?>
                        </td>
                        <td>
                          <?= $attendee['checked_in_at'] === null
                              ? '-'
                              : e(format_event_datetime($attendee['checked_in_at'])) ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
