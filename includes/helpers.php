<?php
/**
 * Small shared helpers (escaping, CSRF, redirect). Owner: Ahmad.
 * Add new helpers here instead of making a second copy elsewhere.
 *
 *     require_once __DIR__ . '/../includes/helpers.php';
 *
 * Needs the session to be started first (includes/session.php) for the CSRF functions.
 */
declare(strict_types=1);

/** Escapes text for safe output in HTML (stops XSS). Use it on everything that came from a user or the database. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Counts characters (not bytes), so names like "احمد" count correctly. Invalid UTF-8 counts as 0. */
function char_length(string $value): int
{
    return (int) preg_match_all('/./su', $value);
}

/** Returns this session's CSRF token, creating one if needed. */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Hidden form field carrying the CSRF token. Put it inside every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** True when the submitted token matches the one stored in the session. */
function csrf_verify(mixed $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

/** Sends the browser to another page and stops the script. */
function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
