<?php
declare(strict_types=1);

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('darsyar_sid');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** در درخواست‌های POST باید فراخوانی شود */
function csrf_check(): void
{
    $sent = (string)($_POST['csrf'] ?? '');
    if (!hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        die('نشست شما منقضی شده یا درخواست نامعتبر است. صفحه را دوباره باز کنید.');
    }
}

function users_count(): int
{
    return (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
}

function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare('SELECT id, username, display_name, role FROM users WHERE id = ?');
        $st->execute([(int)$_SESSION['uid']]);
        $row = $st->fetch();
        $user = $row ?: null;
        if (!$user) {
            unset($_SESSION['uid']);
        }
    }
    return $user;
}

/** ورود اجباری برای صفحات مدیریت */
function require_login(): array
{
    start_session();
    $u = current_user();
    if (!$u) {
        redirect(url('login.php'));
    }
    return $u;
}

function client_ip(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 64);
}

/** محدودیت تلاش ورود: ۵ تلاش ناموفق در ۱۵ دقیقه */
function login_blocked(): bool
{
    $pdo = db();
    $pdo->prepare('DELETE FROM login_attempts WHERE ts < ?')->execute([time() - 3600]);
    $st = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND ts > ?');
    $st->execute([client_ip(), time() - 900]);
    return (int)$st->fetchColumn() >= 5;
}

function login_fail(): void
{
    db()->prepare('INSERT INTO login_attempts(ip, ts) VALUES(?, ?)')->execute([client_ip(), time()]);
}

function login_user(int $id): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $id;
    unset($_SESSION['csrf']);
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([client_ip()]);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool)$p['secure'], true);
    }
    session_destroy();
}

function valid_username(string $u): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_.\-]{3,32}$/', $u);
}

/**
 * ساخت اولین مدیر؛ فقط وقتی هیچ کاربری وجود ندارد کار می‌کند.
 * پس از آن «ثبت‌نام» به‌طور کامل غیرفعال است.
 */
function create_first_admin(string $username, string $display, string $password): ?int
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
            $pdo->exec('ROLLBACK');
            return null;
        }
        $st = $pdo->prepare('INSERT INTO users(username, display_name, password_hash, role) VALUES(?,?,?,\'admin\')');
        $st->execute([$username, $display, password_hash($password, PASSWORD_DEFAULT)]);
        $id = (int)$pdo->lastInsertId();
        $pdo->exec('COMMIT');
        return $id;
    } catch (Throwable $ex) {
        $pdo->exec('ROLLBACK');
        throw $ex;
    }
}
