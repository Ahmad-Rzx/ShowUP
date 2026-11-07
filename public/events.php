<?php
/**
 * Browse upcoming published events, with text, category and mode filters.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/event_helpers.php';

$searchInput = $_GET['q'] ?? '';
$search = is_string($searchInput) ? trim($searchInput) : '';
$categoryId = filter_var($_GET['category_id'] ?? 0, FILTER_VALIDATE_INT);
$categoryId = $categoryId !== false && $categoryId > 0 ? $categoryId : 0;
$modeInput = $_GET['mode'] ?? 'all';
$mode = is_string($modeInput) ? $modeInput : 'all';
if (!in_array($mode, ['all', 'physical', 'online'], true)) {
    $mode = 'all';
}

$categories = fetch_categories($conn);
$sql = <<<SQL
    SELECT e.*, c.name AS category_name, u.full_name AS host_name,
           (SELECT COUNT(*) FROM registrations r
             WHERE r.event_id = e.event_id AND r.status = 'registered') AS attendee_count
    FROM events e
    JOIN categories c ON c.category_id = e.category_id
    JOIN users u ON u.user_id = e.host_id
    WHERE e.status = 'published'
      AND e.start_datetime > NOW()
    SQL;
$params = [];

if ($search !== '') {
    $sql .= ' AND (e.title LIKE ? OR e.description LIKE ? OR u.full_name LIKE ?)';
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($categoryId > 0) {
    $sql .= ' AND e.category_id = ?';
    $params[] = $categoryId;
}

if ($mode !== 'all') {
    $sql .= ' AND e.event_mode = ?';
    $params[] = $mode;
}

$sql .= ' ORDER BY e.start_datetime ASC';
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Browse events';
$activeNav = 'browse-events';

require __DIR__ . '/../includes/header.php';
?>
<main class="page-shell">
  <div class="container">
    <header class="page-header">
      <div>
        <h1>Browse events</h1>
        <p class="page-subtitle">Find your next in-person or online event.</p>
      </div>
      <a class="primary-btn" href="event-create.php">Host an event</a>
    </header>

    <form class="search-panel" method="get" action="events.php">
      <div class="search-row">
        <label class="search-input" for="q">
          <span class="sr-only">Search events</span>
          <input type="search" id="q" name="q" value="<?= e($search) ?>"
                 placeholder="Search events, descriptions or hosts" maxlength="200">
        </label>

        <label class="field" for="category_id">
          <span>Category</span>
          <select id="category_id" name="category_id">
            <option value="0">All categories</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= (int) $category['category_id'] ?>"
                <?= $categoryId === (int) $category['category_id'] ? 'selected' : '' ?>>
                <?= e($category['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="field" for="mode">
          <span>Event type</span>
          <select id="mode" name="mode">
            <option value="all" <?= $mode === 'all' ? 'selected' : '' ?>>All</option>
            <option value="physical" <?= $mode === 'physical' ? 'selected' : '' ?>>Physical</option>
            <option value="online" <?= $mode === 'online' ? 'selected' : '' ?>>Online</option>
          </select>
        </label>

        <button type="submit" class="primary-btn">Search</button>
        <a class="secondary-btn" href="events.php">Clear filters</a>
      </div>
    </form>

    <div class="section-head">
      <h2 class="section-title">Upcoming events</h2>
      <p class="field-hint"><?= count($events) ?> event<?= count($events) === 1 ? '' : 's' ?> found</p>
    </div>

    <?php if ($events === []): ?>
      <div class="empty-state">
        <h2>No events found</h2>
        <p>Try changing your search or filters, or host an event of your own.</p>
        <p><a class="primary-btn" href="event-create.php">Host an event</a></p>
      </div>
    <?php else: ?>
      <div class="event-grid">
        <?php foreach ($events as $event): ?>
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
