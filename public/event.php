<?php
/**
 * Event detail page. Owner: Ahmad.
 *
 * The online meeting link is visible only to the host and to attendees who
 * confirmed. Check-in is open from 30 minutes before the start until the end.
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

$registration = event_fetch_registration($conn, $eventId, $userId);
$review = event_fetch_review($conn, $eventId, $userId);
$isHost = (int) $event['host_id'] === $userId;
$isCancelled = $event['status'] === 'cancelled';
$canManageRsvp = !$isHost && !$isCancelled && (bool) $event['registration_open'];
$isConfirmedAttendee = $registration !== null
    && $registration['status'] === 'registered'
    && $registration['confirmed_at'] !== null;
$isCheckedIn = $registration !== null && $registration['checked_in_at'] !== null;
$canSeeOnlineLink = $isHost || $isConfirmedAttendee;
$checkinIsOpen = !$isCancelled && (bool) $event['checkin_open'];
$checkinWindow = event_checkin_window($event);
$hostReliability = $isHost ? user_reliability_stats($conn, $userId) : null;

$pageTitle = (string) $event['title'];
$activeNav = 'home';

require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell">
  <div class="container">
    <header class="page-header detail-heading">
      <div>
        <div class="badge-row">
          <span class="event-badge"><?= e($event['category_name']) ?></span>
          <span class="mode-badge mode-<?= e($event['event_mode']) ?>">
            <?= $event['event_mode'] === 'online' ? 'Online' : 'In person' ?>
          </span>
          <?php if ($isCancelled): ?>
            <span class="mode-badge mode-cancelled">Cancelled</span>
          <?php endif; ?>
        </div>
        <h1><?= e($event['title']) ?></h1>
        <p class="page-subtitle">Hosted by <?= e($event['host_name']) ?></p>
      </div>

      <?php if ($isHost): ?>
        <div class="button-row">
          <a class="secondary-btn" href="host-dashboard.php">Host dashboard</a>
          <a class="primary-btn" href="event-edit.php?event_id=<?= (int) $event['event_id'] ?>">Edit event</a>
        </div>
      <?php endif; ?>
    </header>

    <?php if ($isCancelled): ?>
      <div class="notice notice-danger" role="status">
        This event was cancelled by the host.
      </div>
    <?php endif; ?>

    <div class="detail-layout">
      <div class="detail-main">
        <section class="detail-card" aria-labelledby="about-title">
          <h2 id="about-title">About this event</h2>
          <p class="detail-description"><?= nl2br(e($event['description'])) ?></p>
        </section>

        <section class="detail-card" aria-labelledby="when-where-title">
          <h2 id="when-where-title">When and where</h2>

          <dl class="detail-list">
            <div>
              <dt>Date and time</dt>
              <dd><?= e(format_event_when($event['start_datetime'], $event['end_datetime'])) ?></dd>
            </div>

            <?php if ($event['event_mode'] === 'physical'): ?>
              <div>
                <dt>Venue</dt>
                <dd><?= e($event['venue_name']) ?></dd>
              </div>
              <div>
                <dt>Address</dt>
                <dd>
                  <?= e($event['address']) ?>
                  <?php if (($event['city'] ?? '') !== ''): ?>
                    <br><?= e($event['city']) ?>
                  <?php endif; ?>
                </dd>
              </div>
            <?php else: ?>
              <div>
                <dt>Online</dt>
                <dd>
                  <?php if (!$isCancelled && $canSeeOnlineLink): ?>
                    <a class="primary-btn" href="<?= e($event['online_link']) ?>" target="_blank" rel="noopener">
                      Join online
                    </a>
                    <p class="field-hint break-word"><?= e($event['online_link']) ?></p>
                  <?php elseif ($isCancelled): ?>
                    <span class="field-hint">This event was cancelled.</span>
                  <?php else: ?>
                    <span class="field-hint">
                      The meeting link is shared with the host and with attendees who have confirmed.
                    </span>
                  <?php endif; ?>
                </dd>
              </div>
            <?php endif; ?>
          </dl>
        </section>

        <?php if ($registration !== null && $registration['status'] === 'registered'
            && $registration['confirmed_at'] !== null): ?>
          <section class="detail-card" aria-labelledby="checkin-title">
            <h2 id="checkin-title">Check in</h2>

            <?php if ($isCheckedIn): ?>
              <div class="notice notice-success">
                You checked in on <?= e(format_event_datetime($registration['checked_in_at'])) ?>.
              </div>
            <?php elseif ($isCancelled): ?>
              <div class="notice">Check-in is closed because the event was cancelled.</div>
            <?php elseif ($checkinIsOpen): ?>
              <p>Enter the six-character code shown by the host.</p>
              <form class="checkin-form" method="post" action="checkin.php">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
                <div class="field">
                  <label for="checkin_code">Check-in code</label>
                  <input class="checkin-code-input" type="text" id="checkin_code" name="checkin_code"
                         maxlength="6" autocomplete="off" autocapitalize="characters" spellcheck="false">
                </div>
                <button type="submit" class="primary-btn">Check in</button>
              </form>
              <p class="field-hint">
                Open until <?= e($checkinWindow['closes']->format('D, j M Y, g:i A')) ?>.
              </p>
            <?php elseif (new DateTimeImmutable('now') < $checkinWindow['opens']): ?>
              <div class="notice">
                Check-in opens on <?= e($checkinWindow['opens']->format('D, j M Y, g:i A')) ?>.
              </div>
            <?php else: ?>
              <div class="notice">Check-in closed on <?= e($checkinWindow['closes']->format('D, j M Y, g:i A')) ?>.</div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($registration !== null && $registration['status'] === 'registered'
            && $registration['confirmed_at'] === null && $canManageRsvp): ?>
          <section class="detail-card" aria-labelledby="confirm-title">
            <h2 id="confirm-title">Confirm your attendance</h2>
            <p>Confirm that you plan to attend. This also unlocks the online meeting link and check-in.</p>
            <form method="post" action="confirm.php">
              <?= csrf_field() ?>
              <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
              <button type="submit" class="primary-btn">I'm still coming</button>
            </form>
          </section>
        <?php endif; ?>

        <?php if ($isCheckedIn): ?>
          <section class="detail-card" aria-labelledby="review-title">
            <h2 id="review-title">Review this event</h2>
            <?php if ($review !== null): ?>
              <p>
                Your rating:
                <strong><?= (int) $review['rating'] ?> / 5</strong>
              </p>
              <?php if (($review['comment'] ?? '') !== ''): ?>
                <p><?= nl2br(e($review['comment'])) ?></p>
              <?php endif; ?>
              <p class="field-hint">Submitted <?= e(format_event_datetime($review['created_at'])) ?>.</p>
            <?php else: ?>
              <p>Thanks for attending. Share a rating and an optional comment.</p>
              <form method="post" action="review.php">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
                <div class="field">
                  <label for="rating">Rating</label>
                  <select id="rating" name="rating" required>
                    <option value="">Choose a rating</option>
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Good</option>
                    <option value="3">3 - Okay</option>
                    <option value="2">2 - Poor</option>
                    <option value="1">1 - Very poor</option>
                  </select>
                </div>
                <div class="field">
                  <label for="comment">Comment <span class="field-hint">(optional, up to 1000 characters)</span></label>
                  <textarea id="comment" name="comment" maxlength="1000"></textarea>
                </div>
                <button type="submit" class="primary-btn">Submit review</button>
              </form>
            <?php endif; ?>
          </section>
        <?php endif; ?>
      </div>

      <aside class="detail-side">
        <section class="side-card" aria-labelledby="attendance-title">
          <h2 id="attendance-title">Attendance</h2>
          <dl class="detail-list">
            <div>
              <dt>Going</dt>
              <dd><?= (int) $event['attendee_count'] ?> people</dd>
            </div>
            <div>
              <dt>Confirmed</dt>
              <dd><?= (int) $event['confirmed_count'] ?> people</dd>
            </div>
            <div>
              <dt>Capacity</dt>
              <dd><?= $event['capacity'] === null ? 'Unlimited' : (int) $event['capacity'] . ' seats' ?></dd>
            </div>
          </dl>
        </section>

        <?php if ($canManageRsvp): ?>
          <section class="side-card" aria-labelledby="rsvp-title">
            <h2 id="rsvp-title">Your RSVP</h2>
            <?php if ($registration !== null && in_array($registration['status'], ['registered', 'waitlisted'], true)): ?>
              <p>
                <span class="status-pill status-<?= e(event_registration_status_class($registration)) ?>">
                  <?= e(event_registration_status($registration)) ?>
                </span>
              </p>
              <?php if ($registration['checked_in_at'] === null): ?>
                <form method="post" action="rsvp.php">
                  <?= csrf_field() ?>
                  <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
                  <input type="hidden" name="action" value="cancel">
                  <button type="submit" class="secondary-btn">Cancel RSVP</button>
                </form>
              <?php else: ?>
                <p class="field-hint">You have checked in, so this RSVP can no longer be cancelled.</p>
              <?php endif; ?>
            <?php else: ?>
              <?php if ($registration !== null && $registration['status'] === 'cancelled'): ?>
                <p class="field-hint">Your previous RSVP was cancelled.</p>
              <?php endif; ?>
              <form method="post" action="rsvp.php">
                <?= csrf_field() ?>
                <input type="hidden" name="event_id" value="<?= (int) $event['event_id'] ?>">
                <input type="hidden" name="action" value="register">
                <button type="submit" class="primary-btn">RSVP to this event</button>
              </form>
              <?php if ($event['capacity'] !== null): ?>
                <p class="field-hint">
                  <?= (int) $event['attendee_count'] ?> of <?= (int) $event['capacity'] ?> seats taken.
                  If the event is full, you will be waitlisted.
                </p>
              <?php endif; ?>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($registration !== null): ?>
          <section class="side-card" aria-labelledby="registration-title">
            <h2 id="registration-title">Your registration</h2>
            <p>
              <span class="status-pill status-<?= e(event_registration_status_class($registration)) ?>">
                <?= e(event_registration_status($registration)) ?>
              </span>
            </p>

            <?php if ($registration['confirmed_at'] !== null): ?>
              <p class="field-hint">
                Confirmed on <?= e(format_event_datetime($registration['confirmed_at'])) ?>.
              </p>
            <?php endif; ?>

            <?php if ($registration['checked_in_at'] !== null): ?>
              <p class="field-hint">
                Checked in on <?= e(format_event_datetime($registration['checked_in_at'])) ?>.
              </p>
            <?php endif; ?>

            <?php if ($registration['status'] === 'cancelled'): ?>
              <p class="field-hint">You cancelled this registration.</p>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <?php if ($isHost): ?>
          <section class="side-card" aria-labelledby="host-tools-title">
            <h2 id="host-tools-title">Host tools</h2>
            <p>Check-in code:</p>
            <p><span class="checkin-code"><?= e($event['checkin_code']) ?></span></p>
            <p class="field-hint">Share this code with attendees when they arrive.</p>

            <?php if ($hostReliability !== null): ?>
              <p class="field-hint">Your reliability: <?= user_reliability_badge_html($hostReliability) ?></p>
            <?php endif; ?>

            <div class="button-row">
              <a class="secondary-btn" href="event-edit.php?event_id=<?= (int) $event['event_id'] ?>">Edit</a>
              <a class="secondary-btn" href="host-dashboard.php">Dashboard</a>
            </div>
          </section>
        <?php endif; ?>
      </aside>
    </div>
  </div>
</main>
<?php require __DIR__ . '/../includes/footer.php'; ?>
