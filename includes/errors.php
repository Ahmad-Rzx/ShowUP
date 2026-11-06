<?php
/**
 * Friendly full-page errors, using the shared layout.
 *
 *     require_once __DIR__ . '/../includes/errors.php';
 *     show_error_page(404, 'Event not found', 'That event does not exist.');
 */
declare(strict_types=1);

/** Sends the right status code and stops the page with a short explanation. */
function show_error_page(int $status, string $title, string $message): never
{
    http_response_code($status);

    $pageTitle = $title;
    $activeNav = '';

    require __DIR__ . '/header.php';
    ?>
    <main class="page-shell">
      <div class="container">
        <div class="empty-state error-state">
          <h1><?= e($title) ?></h1>
          <p><?= e($message) ?></p>
          <p><a class="primary-btn" href="home.php">Back to home</a></p>
        </div>
      </div>
    </main>
    <?php
    require __DIR__ . '/footer.php';
    exit;
}
