<?php
/*
 * Database connection.
 *
 * One PDO per request, in exception mode, with real prepared statements.
 * Supports MySQL (production) and SQLite (the test suite), which is why
 * nothing anywhere uses MySQL-only syntax.
 */

require_once __DIR__ . '/config.php';

/**
 * The shared connection.
 *
 * Passing a PDO in replaces it — that is how tests point the whole application
 * at a throwaway SQLite database without touching config.
 */
function db(?PDO $replace = null): PDO
{
    static $pdo = null;

    if ($replace !== null) {
        $pdo = $replace;
        return $pdo;
    }

    if ($pdo === null) {
        global $db;
        $pdo = db_connect($db);
    }

    return $pdo;
}

/**
 * Opens a connection from a config array.
 */
function db_connect(array $c): PDO
{
    $driver = $c['driver'] ?? 'mysql';

    if ($driver === 'sqlite') {
        $dsn = 'sqlite:' . $c['path'];
        $pdo = new PDO($dsn, null, null, db_options());
        // SQLite ignores foreign keys unless asked.
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    if (empty($c['name'])) {
        throw new RuntimeException(
            'No database configured. Set $db in includes/config.php, and see docs/database-setup.md.'
        );
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
        $c['host'] ?? 'localhost', $c['name'], $c['charset'] ?? 'utf8mb4');

    return new PDO($dsn, $c['user'] ?? '', $c['pass'] ?? '', db_options());
}

function db_options(): array
{
    return [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
}

/**
 * True when the connection is MySQL. A couple of places need to know, because
 * SELECT ... FOR UPDATE is meaningless on SQLite.
 */
function db_is_mysql(?PDO $pdo = null): bool
{
    return ($pdo ?? db())->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
}

/**
 * Has the schema been installed?  Used by the staff app to show a useful
 * message instead of a stack trace on a fresh install.
 */
function db_ready(): bool
{
    try {
        db()->query('SELECT 1 FROM customers LIMIT 1');
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Runs a callback inside a transaction, rolling back if it throws.
 */
function db_transaction(callable $work)
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $work($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

/**
 * 'now' in the café's timezone, formatted for a DATETIME column.
 */
function db_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Europe/Dublin')))->format('Y-m-d H:i:s');
}
