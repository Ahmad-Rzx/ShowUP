<?php
/**
 * Protects a page. Put this at the top of every page that needs a logged-in user:
 *
 *     require_once __DIR__ . '/../includes/auth.php';
 *
 * Not logged in -> redirected to login.php. Logged in -> the page continues and
 * can use $_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'].
 */
declare(strict_types=1);

require_once __DIR__ . '/session.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
