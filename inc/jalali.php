<?php
declare(strict_types=1);

/* ---------- تبدیل میلادی ⇄ شمسی (الگوریتم jdf) ---------- */

function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
        + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd
        + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $days--;
        $gy += 100 * intdiv($days, 36524);
        $days %= 36524;
        if ($days >= 365) {
            $days++;
        }
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $leap = (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28;
    $sal_a = [0, 31, $leap, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    for ($gm = 0; $gm < 13 && $gd > $sal_a[$gm]; $gm++) {
        $gd -= $sal_a[$gm];
    }
    return [$gy, $gm, $gd];
}

const JALALI_MONTHS = [
    1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
    7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند',
];

/** شنبه = ۰ ... جمعه = ۶ */
const JALALI_WEEKDAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

function j_month_len(int $jy, int $jm): int
{
    if ($jm <= 6) {
        return 31;
    }
    if ($jm <= 11) {
        return 30;
    }
    // اسفند: بررسی سال کبیسه با رفت‌وبرگشت تبدیل
    [$gy, $gm, $gd] = jalali_to_gregorian($jy, 12, 30);
    [$jy2, $jm2, $jd2] = gregorian_to_jalali($gy, $gm, $gd);
    return ($jy2 === $jy && $jm2 === 12 && $jd2 === 30) ? 30 : 29;
}

/** «Y-m-d» میلادی ← [jy, jm, jd] */
function ymd_to_j(string $ymd): array
{
    [$y, $m, $d] = array_map('intval', explode('-', $ymd));
    return gregorian_to_jalali($y, $m, $d);
}

/** [jy, jm, jd] ← «Y-m-d» میلادی */
function j_to_ymd(int $jy, int $jm, int $jd): string
{
    [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, $jd);
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

function today_ymd(): string
{
    return date('Y-m-d');
}

/** اندیس روز هفته شمسی (شنبه = ۰) */
function j_weekday(string $ymd): int
{
    $w = (int)date('w', strtotime($ymd . ' 12:00:00')); // یکشنبه = ۰
    return ($w + 1) % 7;
}

function weekday_name(string $ymd): string
{
    return JALALI_WEEKDAYS[j_weekday($ymd)];
}

function ymd_add_days(string $ymd, int $n): string
{
    return date('Y-m-d', strtotime($ymd . ' 12:00:00 ' . ($n >= 0 ? '+' : '') . $n . ' days'));
}

function ymd_diff_days(string $from, string $to): int
{
    $a = new DateTimeImmutable($from . ' 12:00:00');
    $b = new DateTimeImmutable($to . ' 12:00:00');
    return (int)$a->diff($b)->format('%r%a');
}

/** قالب‌های نمایش تاریخ شمسی با ارقام فارسی
 *  short: ۱۴۰۵/۰۷/۱۹ | long: ۱۹ مهر ۱۴۰۵ | day: ۱۹ مهر | full: یکشنبه ۱۹ مهر ۱۴۰۵ | wd: یکشنبه ۱۹ مهر */
function jfmt(?string $ymd, string $style = 'long'): string
{
    if (!$ymd || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return '—';
    }
    [$jy, $jm, $jd] = ymd_to_j($ymd);
    switch ($style) {
        case 'short':
            return fa(sprintf('%04d/%02d/%02d', $jy, $jm, $jd));
        case 'day':
            return fa($jd) . ' ' . JALALI_MONTHS[$jm];
        case 'full':
            return weekday_name($ymd) . ' ' . fa($jd) . ' ' . JALALI_MONTHS[$jm] . ' ' . fa($jy);
        case 'wd':
            return weekday_name($ymd) . ' ' . fa($jd) . ' ' . JALALI_MONTHS[$jm];
        case 'month':
            return JALALI_MONTHS[$jm] . ' ' . fa($jy);
        default:
            return fa($jd) . ' ' . JALALI_MONTHS[$jm] . ' ' . fa($jy);
    }
}

/** تاریخ شمسی تایپ‌شده («۱۴۰۵/۷/۱۹» یا «1405-07-19») ← «Y-m-d» میلادی؛ یا null */
function jparse(string $s): ?string
{
    $s = trim(en_digits($s));
    if (!preg_match('#^(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})$#', $s, $m)) {
        return null;
    }
    [$jy, $jm, $jd] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > j_month_len($jy, $jm)) {
        return null;
    }
    return j_to_ymd($jy, $jm, $jd);
}

/** اعتبارسنجی «Y-m-d» میلادی */
function valid_ymd(?string $s): bool
{
    if (!$s || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
        return false;
    }
    return checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

/** افزودن n ماه شمسی (روز در صورت نیاز به آخر ماه محدود می‌شود) */
function j_add_months(string $ymd, int $n): string
{
    [$jy, $jm, $jd] = ymd_to_j($ymd);
    $total = ($jy * 12 + ($jm - 1)) + $n;
    $ny = intdiv($total, 12);
    $nm = ($total % 12) + 1;
    $nd = min($jd, j_month_len($ny, $nm));
    return j_to_ymd($ny, $nm, $nd);
}

/* ---------- بازه‌های زمانی ---------- */

/** شروع هفته (شنبه) برای تاریخ داده‌شده */
function week_start(string $ymd): string
{
    return ymd_add_days($ymd, -j_weekday($ymd));
}

function month_range(string $ymd): array
{
    [$jy, $jm] = ymd_to_j($ymd);
    return [j_to_ymd($jy, $jm, 1), j_to_ymd($jy, $jm, j_month_len($jy, $jm))];
}

function year_range(string $ymd): array
{
    [$jy] = ymd_to_j($ymd);
    return [j_to_ymd($jy, 1, 1), j_to_ymd($jy, 12, j_month_len($jy, 12))];
}
