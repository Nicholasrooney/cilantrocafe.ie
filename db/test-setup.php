<?php
/*
 * Tests for setup.php's credential writer.
 *
 * This is the one place in the codebase where user input ends up inside a PHP
 * file, so it gets its own tests. A password containing a quote must stay a
 * password and never become code.
 *
 *     php db/test-setup.php
 */

require_once __DIR__ . '/../includes/config.php';

// Pull in just the functions, not the page: setup.php runs its logic on
// include, so load the file and strip everything after the declarations.
$source = file_get_contents(__DIR__ . '/../setup.php');
$source = substr($source, 0, strpos($source, '$errors = [];'));
$source = preg_replace('/^<\?php/', '', $source, 1);
$source = preg_replace('/require_once[^;]+;/', '', $source);
$source = preg_replace('/\$marker\s*=.*?;|\$secretsFile\s*=.*?;|\$schemaFile\s*=.*?;/s', '', $source);
$source = preg_replace('/\$alreadyConfigured\s*=.*?;|\$unlocked\s*=.*?;/s', '', $source);
eval($source);

$passed = $failed = 0;

function check(string $label, $actual, $expected): void
{
    global $passed, $failed;
    if ($actual === $expected) {
        echo "  PASS  $label\n";
        $passed++;
    } else {
        echo "  FAIL  $label\n";
        echo "          expected: " . var_export($expected, true) . "\n";
        echo "          actual:   " . var_export($actual, true) . "\n";
        $failed++;
    }
}

echo "setup.php credential writer\n" . str_repeat('=', 64) . "\n\n";
echo "Injection safety\n----------------\n";

$tmp = sys_get_temp_dir() . '/cilantro-setup-test-' . bin2hex(random_bytes(4)) . '.php';

// Every one of these is a password somebody could plausibly choose, or an
// attempt to break out of the string and run code.
$nasty = [
    "plain-password-123",
    "has'single'quotes",
    'has"double"quotes',
    "back\\slash\\es",
    "'; system('rm -rf /'); \$x = '",
    "'.file_put_contents('/tmp/pwned','x').'",
    "\${'_GET'}[0]",
    "line\nbreak\ttab",
    "€uro and émojis 🌮",
    "<?php echo 'escaped'; ?>",
];

foreach ($nasty as $i => $password) {
    $ok = setup_write_secrets($tmp, [
        'name' => 'u123_cilantro',
        'user' => 'u123_cilantro',
        'pass' => $password,
        'host' => 'localhost',
    ], '$2y$10$abcdefghijklmnopqrstuv', '');

    if (!$ok) {
        check("wrote file for case $i", $ok, true);
        continue;
    }

    // 1. The file must be valid PHP.
    $lint = shell_exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1');
    $valid = str_contains((string) $lint, 'No syntax errors');

    // 2. Including it must reproduce the password exactly, and nothing else.
    $db = ['name' => '', 'user' => '', 'pass' => '', 'host' => ''];
    $staff = ['password_hash' => ''];
    $mail = ['smtp' => ['pass' => '']];
    include $tmp;

    $label = str_replace(["\n", "\t"], ['\\n', '\\t'], mb_substr($password, 0, 28));
    check("valid PHP: $label", $valid, true);
    check("round-trips exactly: $label", $db['pass'], $password);
}

echo "\nContents\n--------\n";

setup_write_secrets($tmp, [
    'name' => 'u9_cilantro', 'user' => 'u9_user', 'pass' => 'secret', 'host' => 'localhost',
], '$2y$10$hashhashhash', 'mailpass');

$db = []; $staff = []; $mail = ['smtp' => []];
include $tmp;
check('database name written', $db['name'], 'u9_cilantro');
check('database user written', $db['user'], 'u9_user');
check('host written',          $db['host'], 'localhost');
check('staff hash written',    $staff['password_hash'], '$2y$10$hashhashhash');
check('smtp password written', $mail['smtp']['pass'], 'mailpass');

// Optional values must be omitted entirely, not written empty, so they do not
// clobber a value already set in config.php.
setup_write_secrets($tmp, [
    'name' => 'u9_cilantro', 'user' => 'u9_user', 'pass' => 'secret', 'host' => 'localhost',
], '', '');
$written = file_get_contents($tmp);
check('no staff line when blank', str_contains($written, 'password_hash'), false);
check('no smtp line when blank',  str_contains($written, 'smtp'), false);

@unlink($tmp);

echo "\n" . str_repeat('=', 64) . "\n";
echo $failed === 0 ? "ALL $passed TESTS PASSED\n" : "$passed passed, $failed FAILED\n";
exit($failed === 0 ? 0 : 1);
