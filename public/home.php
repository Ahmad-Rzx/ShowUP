<?php
/**
 * Home page: authenticated users see their event dashboard.
 * Ahmad owns the database query for real events; this page keeps a small demo
 * dataset for the frontend until that integration is ready.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$userName = $_SESSION['user_name'] ?? 'Friend';

// Ahmad: replace the demo data below with the real event query once it's ready.
$events = [
    [
        'title' => 'City Night Run',
        'category' => 'Sports',
        'date' => 'Tue, Oct 15 • 6:30 PM',
        'location' => 'Green Park, Dubai',
        'description' => 'A relaxed 5K run with a post-race social hangout and light refreshments.',
    ],
    [
        'title' => 'Designing for Real Users',
        'category' => 'Education',
        'date' => 'Thu, Oct 17 • 7:00 PM',
        'location' => 'Innovation Lab, Sharjah',
        'description' => 'A workshop exploring accessible UX practices, user testing, and product thinking.',
    ],
    [
        'title' => 'Open Mic & Coffee',
        'category' => 'Arts',
        'date' => 'Sat, Oct 19 • 8:00 PM',
        'location' => 'The Lantern Café',
        'description' => 'Bring a poem, story, or song and meet local creators in a relaxed setting.',
    ],
    [
        'title' => 'Startup Mixer',
        'category' => 'Networking',
        'date' => 'Mon, Oct 21 • 6:00 PM',
        'location' => 'Skyline Hub, Dubai',
        'description' => 'Meet founders, developers, and students for conversations, introductions, and collaboration.',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Home | ShowUp</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <header class="site-header">
    <div class="container">
      <a href="home.php" class="brand" aria-label="ShowUp home">
        <span class="brand-mark">S</span>
        <span>ShowUp</span>
      </a>

      <nav class="nav-menu" aria-label="Main navigation">
        <a class="nav-link" href="home.php">Home</a>
        <a class="nav-link" href="#events">Explore</a>
        <a class="nav-link" href="#events">Upcoming</a>
        <form method="post" action="logout.php" class="logout-form">
          <?= csrf_field() ?>
          <button type="submit" class="nav-logout">Log out</button>
        </form>
      </nav>
    </div>
  </header>

  <main class="page-shell">
    <div class="container">
      <header class="page-header">
        <div>
          <h1>Hello, <?= e($userName) ?></h1>
          <p class="page-subtitle">Find your next event and plan your week.</p>
        </div>
      </header>

      <section class="search-panel" aria-label="Event search">
        <div class="search-row">
          <div class="search-input">
            <label class="sr-only" for="event-search">Search events</label>
            <input id="event-search" type="search" placeholder="Search events, categories, or locations" aria-label="Search events">
          </div>
        </div>
        <p class="search-hint">Client-side search on the current event list.</p>
      </section>

      <section id="events" aria-labelledby="events-title">
        <div class="page-header">
          <div>
            <h2 id="events-title">Events near you</h2>
          </div>
        </div>

        <?php if (empty($events)): ?>
          <div class="empty-state">
            No events are available right now. Check back soon for new opportunities.
          </div>
        <?php else: ?>
          <div class="event-grid">
            <?php foreach ($events as $event): ?>
              <article class="event-card" data-search="<?= e(strtolower($event['title'] . ' ' . $event['category'] . ' ' . $event['location'])) ?>">
                <span class="event-badge"><?= e($event['category']) ?></span>
                <div>
                  <h3><?= e($event['title']) ?></h3>
                </div>
                <p><?= e($event['description']) ?></p>

                <div class="event-meta">
                  <div class="event-meta-item"><strong>Date</strong><span><?= e($event['date']) ?></span></div>
                  <div class="event-meta-item"><strong>Where</strong><span><?= e($event['location']) ?></span></div>
                </div>

                <div class="event-actions">
                  <a class="secondary-btn" href="#">View details</a>
                  <button type="button" class="primary-btn" aria-label="View event <?= e($event['title']) ?>">Open</button>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <script>
    (function () {
      const searchInput = document.getElementById('event-search');
      const cards = Array.from(document.querySelectorAll('.event-card'));

      if (!searchInput || cards.length === 0) {
        return;
      }

      searchInput.addEventListener('input', function () {
        const query = searchInput.value.trim().toLowerCase();

        cards.forEach(function (card) {
          const match = !query || card.dataset.search.includes(query);
          card.classList.toggle('hidden', !match);
        });
      });
    })();
  </script>
</body>
</html>
