<?php

function getDb(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $cfg    = require __DIR__ . '/config.php';
    $driver = getenv('DB_DRIVER') ?: 'sqlite';

    if ($driver === 'sqlite') {
        $dbFile = __DIR__ . '/../db/expense_app.sqlite';
        $pdo    = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
        initSqlite($pdo);
        return $pdo;
    }

    // MySQL / MariaDB
    $db  = $cfg['db'];
    $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

function initSqlite(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS expense_entries (
            id                   INTEGER PRIMARY KEY AUTOINCREMENT,
            entry_date           TEXT    NOT NULL,
            memo                 TEXT    NOT NULL,
            ref1                 TEXT    NOT NULL DEFAULT '',
            expense_account_code TEXT    NOT NULL,
            expense_account_name TEXT    NOT NULL DEFAULT '',
            amount               REAL    NOT NULL DEFAULT 0,
            vat_account_code     TEXT    NOT NULL DEFAULT '',
            vat_account_name     TEXT    NOT NULL DEFAULT '',
            vat_amount           REAL    NOT NULL DEFAULT 0,
            payment_account_code TEXT    NOT NULL,
            payment_account_name TEXT    NOT NULL DEFAULT '',
            payment_type         TEXT    NOT NULL DEFAULT 'bank',
            total_amount         REAL    NOT NULL DEFAULT 0,
            receipt_path         TEXT,
            sap_doc_entry        INTEGER,
            sap_doc_num          INTEGER,
            status               TEXT    NOT NULL DEFAULT 'pending',
            error_message        TEXT,
            created_at           TEXT    NOT NULL DEFAULT (datetime('now')),
            updated_at           TEXT    NOT NULL DEFAULT (datetime('now'))
        );
    ");
}
