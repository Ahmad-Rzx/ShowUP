<?php
/** Entry point: logged-in users go to the home page, everyone else to login. */
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';

header('Location: ' . (isset($_SESSION['user_id']) ? 'home.php' : 'login.php'));
exit;
