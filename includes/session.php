<?php
/**
 * Starts the PHP session with safe settings.
 * Every page that needs $_SESSION includes this file FIRST, before any output:
 *
 *     require_once __DIR__ . '/../includes/session.php';
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');       // refuse session IDs the server never created (session fixation)
    session_set_cookie_params([
        'lifetime' => 0,                           // cookie lasts until the browser closes
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),   // Secure cookie only when the site runs on HTTPS
        'httponly' => true,                        // JavaScript cannot read the session cookie
        'samesite' => 'Lax',                       // basic protection against cross-site requests
    ]);
    session_start();
}
