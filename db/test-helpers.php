<?php
/*
 * Shared helpers for the test suites: assertions, section headings, and a
 * throwaway in-memory database built from db/schema.sql.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$passed = 0;
$failed = 0;

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

function throws(string $label, string $exceptionClass, callable $fn): void
{
    global $passed, $failed;
    try {
        $fn();
        echo "  FAIL  $label (expected $exceptionClass, nothing thrown)\n";
        $failed++;
    } catch (Throwable $e) {
        if ($e instanceof $exceptionClass) {
            echo "  PASS  $label\n";
            $passed++;
        } else {
            echo "  FAIL  $label (expected $exceptionClass, got " . get_class($e) . ": {$e->getMessage()})\n";
            $failed++;
        }
    }
}

function section(string $title): void
{
    echo "\n" . $title . "\n" . str_repeat('-', strlen($title)) . "\n";
}

/**
 * Builds the schema on SQLite from the MySQL file, so the two cannot drift:
 * if a column is added to schema.sql the tests pick it up automatically.
 *
 * In memory: every call is a guaranteed-clean database, nothing to delete
 * afterwards, and no file for Windows to keep locked between runs.
 */
function fresh_database(): PDO
{
    $pdo = db_connect(['driver' => 'sqlite', 'path' => ':memory:']);
    $sql = (string) file_get_contents(__DIR__ . '/schema.sql');

    foreach (db_sql_statements($sql, 'sqlite') as $statement) {
        try {
            $pdo->exec($statement);
        } catch (PDOException $e) {
            fwrite(STDERR, "Schema translation failed on:\n$statement\n\n" . $e->getMessage() . "\n");
            exit(1);
        }
    }

    // MySQL declared this inline as UNIQUE KEY; SQLite needs it as its own
    // statement. The customer-matching tests depend on it existing.
    $pdo->exec('CREATE UNIQUE INDEX uq_customers_phone_key ON customers(phone_key)');

    db($pdo);
    return $pdo;
}

function finish(): void
{
    global $passed, $failed;
    echo "\n" . str_repeat('=', 64) . "\n";
    echo $failed === 0 ? "ALL $passed TESTS PASSED\n" : "$passed passed, $failed FAILED\n";
    exit($failed === 0 ? 0 : 1);
}
