<?php
declare(strict_types=1);

/** escape برای خروجی HTML */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** تبدیل ارقام لاتین به فارسی */
function fa($v): string
{
    return strtr((string)$v, [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
    ]);
}

/** تبدیل ارقام فارسی و عربی به لاتین (برای ورودی کاربر) */
function en_digits($v): string
{
    return strtr((string)$v, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '٫' => '.', '٬' => '', '،' => '',
    ]);
}

/** عدد فارسی با تعداد اعشار مشخص (۰ = صحیح) و جداکننده هزارگان */
function fnum($n, int $dec = 0): string
{
    $s = number_format((float)$n, $dec, '.', '');
    if ($dec > 0) {
        $s = rtrim(rtrim($s, '0'), '.');
    }
    $parts = explode('.', $s);
    $int = $parts[0];
    $neg = '';
    if ($int !== '' && $int[0] === '-') {
        $neg = '-';
        $int = substr($int, 1);
    }
    $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', '٬', $int);
    $out = $neg . $int;
    if (isset($parts[1]) && $parts[1] !== '') {
        $out .= '٫' . $parts[1];
    }
    return fa($out);
}

/** دقیقه ← «۲ ساعت و ۳۰ دقیقه» */
function fmt_minutes(int $min): string
{
    if ($min <= 0) {
        return '۰ دقیقه';
    }
    $h = intdiv($min, 60);
    $m = $min % 60;
    if ($h > 0 && $m > 0) {
        return fa($h) . ' ساعت و ' . fa($m) . ' دقیقه';
    }
    if ($h > 0) {
        return fa($h) . ' ساعت';
    }
    return fa($m) . ' دقیقه';
}

/** دقیقه ← ساعت اعشاری فارسی (مثلاً ۲٫۵) */
function fmt_hours(int $min): string
{
    return fnum($min / 60, 1);
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/** خواندن عدد صحیح از ورودی (با ارقام فارسی) */
function post_int(string $key, int $default = 0): int
{
    $v = trim(en_digits($_POST[$key] ?? ''));
    if ($v === '' || !preg_match('/^-?\d+$/', $v)) {
        return $default;
    }
    return (int)$v;
}

function get_int(string $key, int $default = 0): int
{
    $v = trim(en_digits($_GET[$key] ?? ''));
    if ($v === '' || !preg_match('/^-?\d+$/', $v)) {
        return $default;
    }
    return (int)$v;
}

function post_str(string $key, int $max = 255): string
{
    $v = trim((string)($_POST[$key] ?? ''));
    return mb_substr($v, 0, $max);
}

/** پیام‌های فلش */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_render(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $out = '';
    foreach ($_SESSION['flash'] as $f) {
        $cls = $f['type'] === 'error' ? 'notice-error' : 'notice-success';
        $out .= '<div class="notice ' . $cls . '"><p>' . e($f['msg']) . '</p>'
            . '<button type="button" class="notice-dismiss" aria-label="بستن">×</button></div>';
    }
    unset($_SESSION['flash']);
    return $out;
}

/** رنگ‌های پیش‌فرض دروس */
function course_palette(): array
{
    return ['#2271b1', '#d63638', '#00a32a', '#dba617', '#8c5cc4', '#e66f1f', '#1d9fa6', '#b3447f'];
}

function valid_color(string $c): bool
{
    return (bool)preg_match('/^#[0-9a-fA-F]{6}$/', $c);
}
