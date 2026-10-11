<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!is_writable(DATA_DIR)) {
        http_response_code(500);
        die('پوشه data قابل نوشتن نیست. دسترسی نوشتن به آن بدهید.');
    }
    $pdo = new PDO('sqlite:' . DB_FILE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        display_name TEXT NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT \'admin\',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL
    )');

    // total_pages: تعداد صفحاتی که برای این درس در بازه برنامه تعیین شده
    $pdo->exec('CREATE TABLE IF NOT EXISTS courses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        start_page INTEGER NOT NULL DEFAULT 1,
        total_pages INTEGER NOT NULL DEFAULT 0,
        daily_minutes INTEGER NOT NULL DEFAULT 0,
        plan_start TEXT NOT NULL,
        plan_end TEXT NOT NULL,
        color TEXT NOT NULL DEFAULT \'#2271b1\',
        notes TEXT NOT NULL DEFAULT \'\',
        status TEXT NOT NULL DEFAULT \'active\',
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS study_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        course_id INTEGER NOT NULL REFERENCES courses(id) ON DELETE CASCADE,
        log_date TEXT NOT NULL,
        pages INTEGER NOT NULL DEFAULT 0,
        minutes INTEGER NOT NULL DEFAULT 0,
        note TEXT NOT NULL DEFAULT \'\',
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_logs_date ON study_logs(log_date)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_logs_course ON study_logs(course_id)');

    $pdo->exec('CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ip TEXT NOT NULL,
        ts INTEGER NOT NULL
    )');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_attempts_ip ON login_attempts(ip, ts)');
}

/* ---------- تنظیمات ---------- */

function setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT key, value FROM settings') as $r) {
            $cache[$r['key']] = $r['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    $st = db()->prepare('INSERT INTO settings(key, value) VALUES(?, ?)
        ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    $st->execute([$key, $value]);
}

/** روزهای تعطیل مطالعه (اندیس شمسی: شنبه = ۰ ... جمعه = ۶) */
function off_days(): array
{
    $raw = setting('off_days', '');
    if ($raw === '') {
        return [];
    }
    return array_values(array_filter(array_map('intval', explode(',', $raw)), fn($v) => $v >= 0 && $v <= 6));
}

function plan_months_default(): int
{
    $m = (int)setting('plan_months', '4');
    return ($m >= 1 && $m <= 24) ? $m : 4;
}
