<?php
require_once __DIR__ . '/_layout.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = staff_login((string) ($_POST['password'] ?? ''));
    if ($error === '') {
        $wanted = $_SESSION['staff_wanted'] ?? 'index.php';
        unset($_SESSION['staff_wanted']);
        // Only ever redirect inside this site.
        if (!preg_match('#^/?[\w./?=-]*$#', $wanted) || str_contains($wanted, '//')) {
            $wanted = 'index.php';
        }
        header('Location: ' . $wanted, true, 303);
        exit;
    }
}

staff_head('Log in');
?>
<form class="card login" method="post" action="login.php">
    <h1>Cilantro staff</h1>
    <p class="sub">The bookings diary.</p>

    <?php if ($error): ?>
        <p class="alert" role="alert"><?= e($error) ?></p>
    <?php endif; ?>

    <label for="password">Café password</label>
    <input type="password" id="password" name="password" required autofocus
           autocomplete="current-password" inputmode="text">

    <button class="btn-p" type="submit">Log in</button>
</form>
<?php staff_foot(); ?>
