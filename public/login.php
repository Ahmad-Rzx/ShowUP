<?php
/**
 * Login page: authenticates the user and redirects to the home page on success.
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/helpers.php';

if (isset($_SESSION['user_id'])) {
    redirect('home.php');
}

$loginError = null;
$registeredMessage = null;
$submittedEmail = '';

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $registeredMessage = 'Account created, please log in';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedEmail = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $loginError = 'Invalid email or password.';
    } elseif ($submittedEmail === '' || !filter_var($submittedEmail, FILTER_VALIDATE_EMAIL) || $password === '') {
        $loginError = 'Invalid email or password.';
    } else {
        $stmt = $conn->prepare('SELECT user_id, full_name, email, password_hash FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $submittedEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['user_id'];
            $_SESSION['user_name'] = (string) $user['full_name'];
            $_SESSION['user_email'] = (string) $user['email'];
            redirect('home.php');
        }

        $loginError = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Log in | ShowUp</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/signup.css">
</head>
<body class="auth-page">
  <main class="auth-card" aria-labelledby="login-title">
    <h1 id="login-title">Welcome back</h1>
    <p class="auth-sub">Log in to discover events and manage your plans.</p>

    <?php if ($registeredMessage !== null): ?>
      <div class="status-message" role="status"><?= e($registeredMessage) ?></div>
    <?php endif; ?>

    <?php if ($loginError !== null): ?>
      <div class="auth-alert" role="alert"><?= e($loginError) ?></div>
    <?php endif; ?>

    <form id="login-form" method="post" action="login.php" novalidate>
      <?= csrf_field() ?>

      <div class="field">
        <label for="email">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          value="<?= e($submittedEmail) ?>"
          autocomplete="email"
          maxlength="120"
          aria-describedby="email-error"
          required
        >
        <p class="field-error" id="email-error" aria-live="polite"><?= e('') ?></p>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="password-row">
          <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            aria-describedby="password-error"
            required
          >
          <button type="button" class="toggle-pw" data-toggle-password data-target="password" aria-pressed="false">Show</button>
        </div>
        <p class="field-error" id="password-error" aria-live="polite"><?= e('') ?></p>
      </div>

      <button type="submit" class="auth-btn">Log in</button>
    </form>

    <p class="auth-switch">
      Need an account? <a href="signup.php">Sign up</a>
    </p>
  </main>

  <script src="assets/js/login.js"></script>
</body>
</html>
