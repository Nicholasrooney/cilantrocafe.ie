<?php
/*
 * Makes a bcrypt hash for the café password.
 *
 * Open /staff/hash.php, type the password you want, copy the hash into
 * includes/secrets/db.php as $staff['password_hash'].
 *
 * Once a password is set this page refuses to run, so it cannot be used to
 * fish for the current one.
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: text/html; charset=utf-8');

if (!empty($staff['password_hash'])) {
    http_response_code(404);
    echo '<p>Not found.</p>';
    echo '<!-- A staff password is already set. Blank $staff[password_hash] to use this page again. -->';
    exit;
}

$hash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['password'])) {
    $hash = password_hash((string) $_POST['password'], PASSWORD_BCRYPT);
}
?>
<!DOCTYPE html>
<html lang="en-IE"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Make a staff password</title>
<link rel="stylesheet" href="staff.css"></head>
<body><main class="wrap">
<div class="card login">
<h1>Set the café password</h1>
<p class="sub">Type the password staff will use. Nothing is saved here — you copy the result into the config yourself.</p>
<form method="post">
  <label for="password">Password</label>
  <input type="password" id="password" name="password" required autofocus>
  <button class="btn-p" type="submit">Make the hash</button>
</form>
<?php if ($hash): ?>
  <p style="margin-top:1.5rem"><strong>Paste this into <code>includes/secrets/db.php</code>:</strong></p>
  <pre class="hashout">$staff['password_hash'] = '<?= e($hash) ?>';</pre>
  <p class="sub">Then reload this page — it will stop working, which is how you know it took.</p>
<?php endif; ?>
</div>
</main></body></html>
