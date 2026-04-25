<?php

function getDb(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $driver = getenv('DB_DRIVER') ?: 'sqlite';

    if ($driver === 'sqlite') {
        $dbFile = __DIR__ . '/../db/expense_app.sqlite';
        $pdo = new PDO('sqlite:' . $dbFile, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
        initSqlite($pdo);
        return $pdo;
    }

    $cfg = require __DIR__ . '/config.php';
    $db  = $cfg['db'];
    $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$db['charset']}";
    $pdo = new PDO($dsn, $db['user'], $db['password'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function getSetting(string $key, string $default = ''): string
{
    try {
        $row = getDb()->prepare('SELECT value FROM settings WHERE key = ?');
        $row->execute([$key]);
        $val = $row->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function getAppConfig(): array
{
    $base = require __DIR__ . '/config.php';
    try {
        $rows = getDb()->query("SELECT key, value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $map = [
            'SAP_BASE_URL'        => ['sap', 'base_url'],
            'SAP_COMPANY_DB'      => ['sap', 'company_db'],
            'SAP_USERNAME'        => ['sap', 'username'],
            'SAP_PASSWORD'        => ['sap', 'password'],
            'APP_CURRENCY'        => ['app', 'currency'],
            'APP_COMPANY'         => ['app', 'company_name'],
            'COMPANY_CRYSTAL_URL' => ['app', 'crystal_url'],
        ];
        foreach ($map as $k => [$section, $field]) {
            if (!empty($rows[$k])) $base[$section][$field] = $rows[$k];
        }
        if (isset($rows['SAP_VERIFY_SSL'])) {
            $base['sap']['verify_ssl'] = filter_var($rows['SAP_VERIFY_SSL'], FILTER_VALIDATE_BOOLEAN);
        }
        if (!empty($rows['APP_VAT_RATE'])) {
            $base['app']['default_vat_rate'] = (float)$rows['APP_VAT_RATE'];
        }
    } catch (Throwable $e) {}
    return $base;
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

        CREATE TABLE IF NOT EXISTS users (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            name          TEXT    NOT NULL,
            email         TEXT    NOT NULL UNIQUE,
            password_hash TEXT    NOT NULL,
            role          TEXT    NOT NULL DEFAULT 'user',
            active        INTEGER NOT NULL DEFAULT 1,
            created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS settings (
            key        TEXT PRIMARY KEY,
            value      TEXT,
            updated_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS account_categories (
            account_code TEXT PRIMARY KEY,
            account_name TEXT NOT NULL,
            category     TEXT NOT NULL,
            sort_order   INTEGER NOT NULL DEFAULT 0,
            created_at   TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS journal_entries (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            entry_date   TEXT    NOT NULL,
            memo         TEXT    NOT NULL DEFAULT '',
            ref1         TEXT    NOT NULL DEFAULT '',
            ref2         TEXT    NOT NULL DEFAULT '',
            sap_doc_entry INTEGER,
            sap_doc_num   INTEGER,
            status        TEXT    NOT NULL DEFAULT 'pending',
            error_message TEXT,
            lines_json    TEXT    NOT NULL DEFAULT '[]',
            created_by    INTEGER,
            created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
        );
    ");

    // Seed default admin if no users exist
    $count = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ((int)$count === 0) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)")
            ->execute(['Administrator', 'admin@company.com', $hash, 'admin']);
    }
}
