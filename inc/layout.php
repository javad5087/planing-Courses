<?php
declare(strict_types=1);

function icon(string $name): string
{
    $paths = [
        'dashboard' => 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z',
        'book' => 'M18 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2zM6 4h5v8l-2.5-1.5L6 12V4z',
        'calendar' => 'M19 4h-1V2h-2v2H8V2H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2zm0 16H5V10h14v10z',
        'edit' => 'M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z',
        'chart' => 'M5 9.2h3V19H5V9.2zM10.6 5h2.8v14h-2.8V5zm5.6 8H19v6h-2.8v-6z',
        'users' => 'M16 11a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm-8 0a3 3 0 1 0-3-3 3 3 0 0 0 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5C15 14.17 10.33 13 8 13zm8 0c-.29 0-.62.02-.97.05A4.2 4.2 0 0 1 17 16.5V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z',
        'settings' => 'M19.14 12.94a7.07 7.07 0 0 0 .05-.94 7.07 7.07 0 0 0-.05-.94l2.03-1.58a.5.5 0 0 0 .12-.64l-1.92-3.32a.5.5 0 0 0-.61-.22l-2.39.96a7 7 0 0 0-1.63-.94l-.36-2.54a.5.5 0 0 0-.5-.42h-3.84a.5.5 0 0 0-.5.42l-.36 2.54a7 7 0 0 0-1.63.94l-2.39-.96a.5.5 0 0 0-.61.22L2.71 8.84a.5.5 0 0 0 .12.64l2.03 1.58a7.07 7.07 0 0 0-.05.94 7.07 7.07 0 0 0 .05.94l-2.03 1.58a.5.5 0 0 0-.12.64l1.92 3.32a.5.5 0 0 0 .61.22l2.39-.96a7 7 0 0 0 1.63.94l.36 2.54a.5.5 0 0 0 .5.42h3.84a.5.5 0 0 0 .5-.42l.36-2.54a7 7 0 0 0 1.63-.94l2.39.96a.5.5 0 0 0 .61-.22l1.92-3.32a.5.5 0 0 0-.12-.64zM12 15.5A3.5 3.5 0 1 1 15.5 12 3.5 3.5 0 0 1 12 15.5z',
        'menu' => 'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z',
    ];
    $d = $paths[$name] ?? $paths['dashboard'];
    return '<svg class="mi" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path fill="currentColor" d="' . $d . '"/></svg>';
}

/** ساختار منو به سبک وردپرس: [آیکون، عنوان، فایل اصلی، [زیرمنوها: [فایل?query, عنوان]]] */
function admin_menu(): array
{
    return [
        ['dashboard', 'پیشخوان', 'dashboard.php', [
            ['dashboard.php', 'پیشخوان'],
        ]],
        ['book', 'دروس', 'courses.php', [
            ['courses.php', 'همه دروس'],
            ['course-edit.php', 'افزودن درس'],
        ]],
        ['calendar', 'برنامه مطالعه', 'plan.php', [
            ['plan.php', 'برنامه امروز و ماه‌ها'],
        ]],
        ['edit', 'ثبت مطالعه', 'log.php', [
            ['log.php', 'ثبت مطالعهٔ جدید'],
            ['history.php', 'تاریخچهٔ مطالعه'],
        ]],
        ['chart', 'آمار', 'stats.php', [
            ['stats.php?p=day', 'آمار روزانه'],
            ['stats.php?p=week', 'آمار هفتگی'],
            ['stats.php?p=month', 'آمار ماهانه'],
            ['stats.php?p=year', 'آمار سالانه'],
        ]],
        ['users', 'کاربران', 'users.php', [
            ['users.php', 'همهٔ کاربران'],
            ['user-edit.php', 'افزودن مدیر'],
            ['profile.php', 'پروفایل من'],
        ]],
        ['settings', 'تنظیمات', 'settings.php', [
            ['settings.php?tab=general', 'عمومی'],
            ['settings.php?tab=plan', 'برنامه‌ریزی'],
        ]],
    ];
}

function admin_header(string $title, ?string $actionLabel = null, ?string $actionUrl = null): void
{
    $user = current_user();
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $query = $_SERVER['QUERY_STRING'] ?? '';
    $siteTitle = setting('site_title', 'برنامه مطالعه');
    $v = APP_VERSION;
    ?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?> ‹ <?= e($siteTitle) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/font-face.css">
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>?v=<?= e($v) ?>">
</head>
<body class="admin">
<div id="adminbar">
    <button type="button" id="menu-toggle" aria-label="منو"><?= icon('menu') ?></button>
    <a class="site-name" href="<?= e(url('admin/dashboard.php')) ?>"><?= e($siteTitle) ?></a>
    <span class="spacer"></span>
    <span class="today-chip" title="تاریخ امروز"><?= e(jfmt(today_ymd(), 'full')) ?></span>
    <span class="user-chip"><?= e($user['display_name'] ?? '') ?></span>
    <a class="logout" href="<?= e(url('logout.php')) ?>?t=<?= e(csrf_token()) ?>">خروج</a>
</div>
<div id="wrap">
<nav id="adminmenu" aria-label="منوی اصلی">
    <ul>
    <?php foreach (admin_menu() as [$ic, $label, $main, $subs]):
        $isCurrent = false;
        foreach ($subs as [$link]) {
            $parts = explode('?', $link, 2);
            if ($parts[0] === $script) {
                $isCurrent = true;
                break;
            }
        }
        // course-edit.php نیز زیرمجموعهٔ دروس است
        if ($main === 'courses.php' && $script === 'course-edit.php') {
            $isCurrent = true;
        }
        ?>
        <li class="menu-top<?= $isCurrent ? ' current' : '' ?>">
            <a class="menu-link" href="<?= e(url('admin/' . $subs[0][0])) ?>"><?= icon($ic) ?><span><?= e($label) ?></span></a>
            <?php if (count($subs) > 1): ?>
            <ul class="submenu">
                <?php foreach ($subs as [$link, $sl]):
                    $parts = explode('?', $link, 2);
                    $active = false;
                    if ($parts[0] === $script) {
                        if (isset($parts[1])) {
                            parse_str($parts[1], $want);
                            parse_str($query, $have);
                            $active = true;
                            foreach ($want as $k => $val) {
                                $cur = $have[$k] ?? ($k === 'p' ? 'day' : ($k === 'tab' ? 'general' : ''));
                                if ((string)$cur !== (string)$val) {
                                    $active = false;
                                }
                            }
                        } else {
                            $active = true;
                        }
                    }
                    ?>
                    <li<?= $active ? ' class="active"' : '' ?>><a href="<?= e(url('admin/' . $link)) ?>"><?= e($sl) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
    </ul>
</nav>
<main id="content">
    <div class="wrap-inner">
        <h1 class="page-title"><?= e($title) ?>
            <?php if ($actionLabel && $actionUrl): ?>
                <a href="<?= e($actionUrl) ?>" class="page-title-action"><?= e($actionLabel) ?></a>
            <?php endif; ?>
        </h1>
        <?= flash_render() ?>
<?php
}

function admin_footer(): void
{
    ?>
    </div>
    <footer id="footer">برنامهٔ مطالعه · PHP و SQLite · نسخهٔ <?= e(fa(APP_VERSION)) ?></footer>
</main>
</div>
<script src="<?= e(url('assets/js/admin.js')) ?>?v=<?= e(APP_VERSION) ?>"></script>
</body>
</html>
<?php
}

/** نوار پیشرفت */
function progress_bar(int $pct, string $color = '#2271b1'): string
{
    $pct = max(0, min(100, $pct));
    return '<div class="progress" role="progressbar" aria-valuenow="' . $pct . '" aria-valuemin="0" aria-valuemax="100">'
        . '<span style="width:' . $pct . '%;background:' . e($color) . '"></span></div>';
}

/** فیلد تاریخ شمسی با سه لیست کشویی؛ مقدار پنهان به‌صورت «Y-m-d» میلادی ارسال می‌شود */
function jdate_field(string $name, ?string $ymd): string
{
    $ymd = ($ymd && valid_ymd($ymd)) ? $ymd : today_ymd();
    return '<span class="jdate" data-name="' . e($name) . '" data-value="' . e($ymd) . '"></span>';
}
