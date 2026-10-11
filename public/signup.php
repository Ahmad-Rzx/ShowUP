<?php
/**
 * Signup page: shows the form (GET) and creates the account (POST). Owner: Ahmad.
 *
 * Flow: validate -> check duplicate email -> hash password -> INSERT -> redirect to login.php?registered=1
 */
declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';     // session first, before any output
require_once __DIR__ . '/../config/database.php';      // gives us $conn (MySQLi)
require_once __DIR__ . '/../includes/helpers.php';

// Already logged in? Nothing to do here.
if (isset($_SESSION['user_id'])) {
    redirect('home.php');
}

$errors = [];                                          // field name => message
$old    = ['full_name' => '', 'email' => '', 'city' => ''];   // values to refill the form

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim((string) ($_POST['full_name'] ?? ''));
    $old['email']     = strtolower(trim((string) ($_POST['email'] ?? '')));
    $old['city']      = trim((string) ($_POST['city'] ?? ''));
    $password         = (string) ($_POST['password'] ?? '');          // never trim passwords
    $confirm          = (string) ($_POST['confirm_password'] ?? '');

    // 1. CSRF check: the form must be the one WE served
    if (!csrf_verify($_POST['csrf_token'] ?? null)) {
        $errors['form'] = 'Your session expired. Please try again.';
    }

    // 2. Validate every field (the JavaScript does the same checks, but JS can be bypassed)
    $nameLength = char_length($old['full_name']);
    if ($nameLength < 2 || $nameLength > 100) {
        $errors['full_name'] = 'Enter your full name (2 to 100 characters).';
    }

    if ($old['email'] === '' || strlen($old['email']) > 120
        || !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }

    if (char_length($old['city']) > 80) {
        $errors['city'] = 'City must be 80 characters or fewer.';
    }

    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 72) {                  // bcrypt only reads the first 72 bytes
        $errors['password'] = 'Password can be at most 72 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors['password'] = 'Password must contain at least one letter and one number.';
    }

    if ($confirm !== $password) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    // 3. Does this email already have an account? (prepared statement, as in the course guide)
    if (!isset($errors['email']) && !isset($errors['form'])) {
        $stmt = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
        $stmt->bind_param('s', $old['email']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors['email'] = 'That email is already registered. Try logging in.';
        }
        $stmt->close();
    }

    // 4. Everything valid: create the account
    if (!$errors) {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);   // never store the real password
            $city         = $old['city'] !== '' ? $old['city'] : null;

            $stmt = $conn->prepare(
                'INSERT INTO users (full_name, email, password_hash, city) VALUES (?, ?, ?, ?)'
            );
            $stmt->bind_param('ssss', $old['full_name'], $old['email'], $passwordHash, $city);
            $stmt->execute();
            $stmt->close();

            unset($_SESSION['csrf_token']);                   // fresh token for the next form
            redirect('login.php?registered=1');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() === 1062) {                    // two people signed up with the same email at once
                $errors['email'] = 'That email is already registered. Try logging in.';
            } else {
                error_log('Signup failed: ' . $ex->getMessage());
                $errors['form'] = 'Something went wrong on our side. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign up | ShowUp</title>
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="stylesheet" href="assets/css/signup.css">
</head>
<body class="auth-page">
  <main class="auth-card">
    <h1>Create your account</h1>
    <p class="auth-sub">Join ShowUp to find events, RSVP and host your own.</p>

    <?php if (isset($errors['form'])): ?>
      <div class="auth-alert" role="alert"><?= e($errors['form']) ?></div>
    <?php endif; ?>

    <form id="signup-form" method="post" action="signup.php" novalidate>
      <?= csrf_field() ?>

      <div class="field">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>"
               autocomplete="name" maxlength="100" required
               <?= isset($errors['full_name']) ? 'aria-invalid="true"' : '' ?>>
        <p class="field-error" id="full_name-error"><?= e($errors['full_name'] ?? '') ?></p>
      </div>

      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= e($old['email']) ?>"
               autocomplete="email" maxlength="120" required
               <?= isset($errors['email']) ? 'aria-invalid="true"' : '' ?>>
        <p class="field-error" id="email-error"><?= e($errors['email'] ?? '') ?></p>
      </div>

      <div class="field">
        <label for="city">City <span class="optional">(optional)</span></label>
        <input type="text" id="city" name="city" value="<?= e($old['city']) ?>"
               autocomplete="address-level2" maxlength="80"
               <?= isset($errors['city']) ? 'aria-invalid="true"' : '' ?>>
        <p class="field-error" id="city-error"><?= e($errors['city'] ?? '') ?></p>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <div class="password-row">
          <input type="password" id="password" name="password"
                 autocomplete="new-password" maxlength="72" required
                 <?= isset($errors['password']) ? 'aria-invalid="true"' : '' ?>>
          <button type="button" class="toggle-pw" data-target="password" aria-pressed="false">Show</button>
        </div>
        <p class="field-hint" id="password-hint">At least 8 characters, with a letter and a number.</p>
        <p class="field-error" id="password-error"><?= e($errors['password'] ?? '') ?></p>
      </div>

      <div class="field">
        <label for="confirm_password">Confirm password</label>
        <div class="password-row">
          <input type="password" id="confirm_password" name="confirm_password"
                 autocomplete="new-password" maxlength="72" required
                 <?= isset($errors['confirm_password']) ? 'aria-invalid="true"' : '' ?>>
          <button type="button" class="toggle-pw" data-target="confirm_password" aria-pressed="false">Show</button>
        </div>
        <p class="field-error" id="confirm_password-error"><?= e($errors['confirm_password'] ?? '') ?></p>
      </div>

      <button type="submit" class="auth-btn" id="signup-submit">Create account</button>
    </form>

    <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
  </main>

  <script src="assets/js/signup.js"></script>
</body>
</html>
