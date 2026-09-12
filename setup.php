<?php
/*
 * One-off guided setup.
 *
 * Replaces the fiddly parts of getting started: importing SQL through
 * phpMyAdmin and hand-writing a PHP credentials file. Fill in the database
 * details, press the button, and this creates the tables, writes the secrets
 * file and locks itself.
 *
 * Two locks, because a page that writes a PHP file deserves them:
 *
 *  1. It refuses to run unless includes/secrets/SETUP-ALLOWED exists. You
 *     create that empty file yourself in File Manager, which proves you have
 *     access to the server and not just the URL.
 *  2. It refuses to run once a database is already configured.
 *
 * On success it deletes the marker, so the page is dead from then on.
 *
 * Credentials are written with var_export(), never string interpolation, so
 * nothing typed into the form can become executable code.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$marker      = __DIR__ . '/includes/secrets/SETUP-ALLOWED';
$secretsFile = __DIR__ . '/includes/secrets/db.php';
$schemaFile  = __DIR__ . '/db/schema.sql';

$alreadyConfigured = !empty($db['name']) && db_ready();
$unlocked          = is_file($marker);

/**
 * Writes the credentials file. Only ever receives values that have already
 * been validated, and every one goes through var_export().
 */
function setup_write_secrets(string $path, array $dbValues, string $passwordHash, string $smtpPass): bool
{
    $lines = [
        '<?php',
        '/*',
        ' * Credentials for cilantrocafe.ie.',
        ' *',
        ' * Written by setup.php on ' . date('j F Y') . '.',
        ' * This file is gitignored and blocked from the web. Never commit it.',
        ' */',
        '',
        '$db[\'name\'] = ' . var_export($dbValues['name'], true) . ';',
        '$db[\'user\'] = ' . var_export($dbValues['user'], true) . ';',
        '$db[\'pass\'] = ' . var_export($dbValues['pass'], true) . ';',
        '$db[\'host\'] = ' . var_export($dbValues['host'], true) . ';',
        '',
    ];

    if ($passwordHash !== '') {
        $lines[] = '// Staff login for /staff/';
        $lines[] = '$staff[\'password_hash\'] = ' . var_export($passwordHash, true) . ';';
        $lines[] = '';
    }

    if ($smtpPass !== '') {
        $lines[] = '// Mailbox password for sending booking confirmations';
        $lines[] = '$mail[\'smtp\'][\'pass\'] = ' . var_export($smtpPass, true) . ';';
        $lines[] = '';
    }

    $dir = dirname($path);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
        return false;
    }

    if (@file_put_contents($path, implode("\n", $lines) . "\n", LOCK_EX) === false) {
        return false;
    }

    @chmod($path, 0640);
    return true;
}

/**
 * Runs db/schema.sql. Every statement is CREATE TABLE IF NOT EXISTS, so this
 * is safe to run against a database that is already set up.
 */
function setup_run_schema(PDO $pdo, string $schemaFile): array
{
    $sql = (string) file_get_contents($schemaFile);
    $sql = preg_replace('/--[^\n]*/', '', $sql);

    $made = [];
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        $pdo->exec($statement);
        if (preg_match('/CREATE TABLE IF NOT EXISTS\s+(\w+)/i', $statement, $m)) {
            $made[] = $m[1];
        }
    }

    return $made;
}

$errors = [];
$done   = false;
$result = [];

$form = [
    'host'     => 'localhost',
    'name'     => '',
    'user'     => '',
    'pass'     => '',
    'staffpw'  => '',
    'smtppass' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $unlocked && !$alreadyConfigured) {
    foreach ($form as $field => $default) {
        $form[$field] = trim((string) ($_POST[$field] ?? $default));
    }

    // Hostinger names are like u123456789_cilantro. Keep it tight: these two
    // end up inside a DSN.
    foreach (['name' => 'Database name', 'user' => 'Database user'] as $field => $label) {
        if ($form[$field] === '') {
            $errors[$field] = "$label is required.";
        } elseif (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $form[$field])) {
            $errors[$field] = "$label can only contain letters, numbers and underscores.";
        }
    }

    if (!preg_match('/^[A-Za-z0-9._:-]{1,120}$/', $form['host'])) {
        $errors['host'] = 'That host does not look right. It is almost always localhost.';
    }

    if ($form['pass'] === '') {
        $errors['pass'] = 'Database password is required.';
    }

    if (!$errors) {
        try {
            $pdo = db_connect([
                'driver'  => 'mysql',
                'host'    => $form['host'],
                'name'    => $form['name'],
                'user'    => $form['user'],
                'pass'    => $form['pass'],
                'charset' => 'utf8mb4',
            ]);

            $result['tables'] = setup_run_schema($pdo, $schemaFile);

            $hash = $form['staffpw'] !== ''
                ? password_hash($form['staffpw'], PASSWORD_BCRYPT)
                : ($staff['password_hash'] ?? '');

            if (!setup_write_secrets($secretsFile, $form, $hash, $form['smtppass'])) {
                $errors['form'] = 'Connected to the database, but could not write '
                                . 'includes/secrets/db.php. Check the folder is writable.';
            } else {
                @unlink($marker);       // lock the page behind us
                $done = true;
                $result['staff_password_set'] = $form['staffpw'] !== '';
                $result['smtp_password_set']  = $form['smtppass'] !== '';
            }

        } catch (PDOException $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'Access denied')) {
                $errors['form'] = 'The database refused those details. Check the user, '
                                . 'password, and that the user has been given access to the database.';
            } elseif (str_contains($message, 'Unknown database')) {
                $errors['form'] = 'That database does not exist yet. Create it in hPanel '
                                . 'under Databases, then come back.';
            } else {
                $errors['form'] = 'Could not connect: ' . $message;
            }
        } catch (Throwable $e) {
            $errors['form'] = 'Setup failed: ' . $e->getMessage();
        }
    }
}
?><!DOCTYPE html>
<html lang="en-IE">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Set up Cilantro Café</title>
<link rel="stylesheet" href="<?= e(asset('staff/staff.css')) ?>">
<style>
  .wrap { max-width: 34rem; }
  .step { counter-increment: step; }
  code { background: #eef2ee; padding: 0.1rem 0.35rem; border-radius: 4px; font-size: 0.9em; }
  .ok { color: #146b4f; font-weight: 600; }
</style>
</head>
<body>
<header class="bar"><span class="bar-brand">Cilantro <span>setup</span></span></header>
<main class="wrap">

<?php if ($done): ?>

    <div class="card">
        <h1>Done.</h1>
        <p class="ok">The database is set up and the site is connected to it.</p>
        <p>Tables ready: <?= e(implode(', ', $result['tables'])) ?>.</p>
        <p>Credentials were written to <code>includes/secrets/db.php</code>, which is
           blocked from the web and never goes into Git.</p>
        <p>This page has locked itself and will now return "Not found".</p>

        <h2>Next</h2>
        <ol>
            <li><a href="staff/">Open the staff diary</a>
                <?php if ($result['staff_password_set']): ?>
                    and log in with the password you just set.
                <?php else: ?>
                    and log in.
                <?php endif; ?></li>
            <li><a href="booking.php">Make a test booking</a> and watch it appear in the diary.</li>
            <li>If you have old bookings in the CSV, run
                <code>php db/import-csv.php --commit</code> over SSH.</li>
        </ol>
    </div>

<?php elseif ($alreadyConfigured): ?>

    <div class="card">
        <h1>Already set up</h1>
        <p>The site is connected to its database and the tables exist. There is
           nothing to do here.</p>
        <p><a class="btn-p" href="staff/">Open the staff diary</a></p>
    </div>

<?php elseif (!$unlocked): ?>

    <div class="card">
        <h1>Setup is locked</h1>
        <p>To unlock it, create an empty file on the server at:</p>
        <p><code>public_html/includes/secrets/SETUP-ALLOWED</code></p>
        <p>In hPanel, that is <strong>File Manager</strong> → open
           <code>includes/secrets</code> → <strong>New File</strong> → name it
           exactly <code>SETUP-ALLOWED</code> with no extension.</p>
        <p class="sub">This is here so that only somebody with access to the server can
           point this site at a database — knowing the web address is not enough.
           The file is deleted automatically when setup finishes.</p>
        <p><a class="btn-s" href="setup.php">I have created it — try again</a></p>
    </div>

<?php else: ?>

    <form class="card form" method="post" action="setup.php" autocomplete="off">
        <h1>Set up the database</h1>
        <p class="sub">Create the database first in hPanel → <strong>Databases → MySQL
           Databases</strong>, giving the user full access. Then put the same three
           values here.</p>

        <?php if (isset($errors['form'])): ?>
            <p class="alert" role="alert"><?= e($errors['form']) ?></p>
        <?php endif; ?>

        <div class="field">
            <label for="name">Database name</label>
            <input id="name" name="name" value="<?= e($form['name']) ?>" placeholder="u123456789_cilantro" required>
            <?php if (isset($errors['name'])): ?><p class="err"><?= e($errors['name']) ?></p><?php endif; ?>
        </div>

        <div class="field">
            <label for="user">Database user</label>
            <input id="user" name="user" value="<?= e($form['user']) ?>" placeholder="u123456789_cilantro" required>
            <?php if (isset($errors['user'])): ?><p class="err"><?= e($errors['user']) ?></p><?php endif; ?>
        </div>

        <div class="field">
            <label for="pass">Database password</label>
            <input id="pass" name="pass" type="password" required>
            <?php if (isset($errors['pass'])): ?><p class="err"><?= e($errors['pass']) ?></p><?php endif; ?>
        </div>

        <div class="field">
            <label for="host">Database host</label>
            <input id="host" name="host" value="<?= e($form['host']) ?>">
            <p class="sub">Leave as <code>localhost</code> unless Hostinger told you otherwise.</p>
            <?php if (isset($errors['host'])): ?><p class="err"><?= e($errors['host']) ?></p><?php endif; ?>
        </div>

        <hr style="border:0;border-top:1px solid var(--line);margin:1.5rem 0">

        <div class="field">
            <label for="staffpw">Staff password <span class="opt">(optional)</span></label>
            <input id="staffpw" name="staffpw" type="password">
            <p class="sub">The password staff type at <code>/staff/</code>. Leave blank to keep
               whatever is already set.</p>
        </div>

        <div class="field">
            <label for="smtppass">Mailbox password <span class="opt">(optional)</span></label>
            <input id="smtppass" name="smtppass" type="password">
            <p class="sub">Only if you have created <code>bookings@cilantrocafe.ie</code> in
               hPanel. Leave blank for now — booking emails still send without it.</p>
        </div>

        <div class="form-do">
            <button class="btn-p" type="submit">Create the tables and connect</button>
        </div>

        <p class="sub" style="margin-top:1rem">Nothing is written until the connection is
           proven to work.</p>
    </form>

<?php endif; ?>

</main>
</body>
</html>
