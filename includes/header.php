<?php
/**
 * Shared page header and navigation. Owner: Ahmad.
 *
 * A page sets these before including this file:
 *   $pageTitle  string  shown in the browser tab
 *   $activeNav  string  home, host-create, host-dashboard or profile
 *
 * Sultan: add Browse events (events.php) and My events (my-events.php) to the
 * navigation when those pages are ready. Keep this file in sync with him.
 */
declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/helpers.php';

$pageTitle = $pageTitle ?? 'ShowUp';
$activeNav = $activeNav ?? '';
$currentUserName = (string) ($_SESSION['user_name'] ?? '');
$flash = flash_pull();
$flashRole = $flash !== null && $flash['type'] === 'error' ? 'alert' : 'status';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | ShowUp</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <header class="site-header">
    <div class="container">
      <a href="home.php" class="brand" aria-label="ShowUp home">
        <span class="brand-mark" aria-hidden="true">S</span>
        <span>ShowUp</span>
      </a>

      <nav class="nav-menu" aria-label="Main navigation">
        <a class="nav-link<?= $activeNav === 'home' ? ' is-active' : '' ?>" href="home.php">Home</a>
        <a class="nav-link<?= $activeNav === 'host-create' ? ' is-active' : '' ?>" href="event-create.php">Host an event</a>
        <a class="nav-link<?= $activeNav === 'host-dashboard' ? ' is-active' : '' ?>" href="host-dashboard.php">Host dashboard</a>

        <?php if ($currentUserName !== ''): ?>
          <a class="nav-user<?= $activeNav === 'profile' ? ' is-active' : '' ?>"
             href="profile.php" title="View your profile"><?= e($currentUserName) ?></a>
        <?php endif; ?>

        <form method="post" action="logout.php" class="logout-form">
          <?= csrf_field() ?>
          <button type="submit" class="nav-logout">Log out</button>
        </form>
      </nav>
    </div>
  </header>

  <?php if ($flash !== null): ?>
    <div class="container flash-wrap">
      <div class="flash flash-<?= e($flash['type']) ?>" role="<?= e($flashRole) ?>">
        <?= e($flash['message']) ?>
      </div>
    </div>
  <?php endif; ?>
