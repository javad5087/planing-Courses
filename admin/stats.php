<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_login();

$today = today_ymd();
$p = (string)($_GET['p'] ?? 'day');
if (!in_array($p, ['day', 'week', 'month', 'year'], true)) {
    $p = 'day';
}
$ref = (string)($_GET['r'] ?? '');
if (!valid_ymd($ref) || $ref > $today) {
    $ref = $today;
}

$info = period_info($p, $ref);
$courses = courses_all(false);
$perCourse = stats_by_course($info['from'], $info['to']);
$total = stats_total($info['from'], $info['to']);

// تعداد روزهای گذشتهٔ بازه (برای میانگین)
$elapsedTo = min($info['to'], $today);
$elapsed = max(1, ymd_diff_days($info['from'], $elapsedTo) + 1);
$avgPages = $total['pages'] / $elapsed;
$avgMin = $total['minutes'] / $elapsed;

$nBuckets = ['day' => 14, 'week' => 12, 'month' => 12, 'year' => 5][$p];
$buckets = trailing_buckets($p, $ref, $nBuckets);
$series = bucket_series($buckets);

// ریز بازه
$sub = [];
if ($p === 'week' || $p === 'month') {
    for ($d = $info['from']; $d <= $info['to']; $d = ymd_add_days($d, 1)) {
        $sub[] = ['label' => jfmt($d, 'wd'), 'from' => $d, 'to' => $d];
    }
} elseif ($p === 'year') {
    [$jy] = ymd_to_j($info['from']);
    for ($m = 1; $m <= 12; $m++) {
        $sub[] = ['label' => JALALI_MONTHS[$m], 'from' => j_to_ymd($jy, $m, 1), 'to' => j_to_ymd($jy, $m, j_month_len($jy, $m))];
    }
}
$subSeries = $sub ? bucket_series($sub) : [];

$dayLogs = [];
if ($p === 'day') {
    $st = db()->prepare('SELECT l.*, c.title, c.color FROM study_logs l JOIN courses c ON c.id = l.course_id
        WHERE l.log_date = ? ORDER BY l.id');
    $st->execute([$ref]);
    $dayLogs = $st->fetchAll();
}

$prevOk = true;
$nextOk = $info['next'] <= $today;

function stats_url(string $p, ?string $r = null): string
{
    $q = ['p' => $p];
    if ($r !== null && $r !== today_ymd()) {
        $q['r'] = $r;
    }
    return url('admin/stats.php?' . http_build_query($q));
}

admin_header('آمار ' . $info['title']);
?>
<div class="tabs">
    <?php foreach (['day' => 'روزانه', 'week' => 'هفتگی', 'month' => 'ماهانه', 'year' => 'سالانه'] as $k => $lbl): ?>
        <a href="<?= e(stats_url($k, $ref)) ?>" class="<?= $k === $p ? 'active' : '' ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
</div>

<div class="period-nav">
    <a class="button" href="<?= e(stats_url($p, $info['prev'])) ?>">‹ قبلی</a>
    <span class="label"><?= e($info['label']) ?></span>
    <?php if ($nextOk): ?>
        <a class="button" href="<?= e(stats_url($p, $info['next'])) ?>">بعدی ›</a>
    <?php else: ?>
        <span class="button" style="opacity:.5;cursor:default">بعدی ›</span>
    <?php endif; ?>
    <?php if ($ref !== $today): ?><a class="button" href="<?= e(stats_url($p)) ?>">امروز</a><?php endif; ?>
</div>

<div class="grid grid-4" style="margin-bottom:20px">
    <div class="stat-card"><div class="lbl">صفحات خوانده‌شده</div>
        <div class="num"><?= e(fnum($total['pages'])) ?></div></div>
    <div class="stat-card"><div class="lbl">ساعت مطالعه</div>
        <div class="num"><?= e(fmt_hours($total['minutes'])) ?></div>
        <div class="sub"><?= e(fmt_minutes($total['minutes'])) ?></div></div>
    <div class="stat-card"><div class="lbl">روزهای فعال</div>
        <div class="num"><?= e(fnum($total['days'])) ?></div>
        <?php if ($p !== 'day'): ?><div class="sub">از <?= e(fnum($elapsed)) ?> روز</div><?php endif; ?></div>
    <div class="stat-card"><div class="lbl">میانگین روزانه</div>
        <div class="num"><?= e(fnum($avgPages, 1)) ?> <small style="font-size:13px;font-weight:400">صفحه</small></div>
        <div class="sub"><?= e(fmt_minutes((int)round($avgMin))) ?></div></div>
</div>

<div class="grid grid-2">
    <div class="postbox box-static">
        <div class="postbox-head"><h2>صفحات (به تفکیک درس)</h2></div>
        <div class="postbox-body"><?= svg_stacked_bars($buckets, $series, $courses, 'pages') ?></div>
    </div>
    <div class="postbox box-static">
        <div class="postbox-head"><h2>ساعت مطالعه (به تفکیک درس)</h2></div>
        <div class="postbox-body"><?= svg_stacked_bars($buckets, $series, $courses, 'minutes') ?></div>
    </div>
</div>
<div class="legend" style="margin:-6px 0 20px">
    <?php foreach ($courses as $c): ?>
        <span><i class="dot" style="background:<?= e($c['color']) ?>"></i><?= e($c['title']) ?></span>
    <?php endforeach; ?>
</div>

<div class="postbox box-static">
    <div class="postbox-head"><h2>به تفکیک درس — <?= e($info['label']) ?></h2></div>
    <div class="postbox-body">
        <?php if (!$courses): ?>
            <div class="empty">درسی وجود ندارد.</div>
        <?php else: ?>
        <table class="list">
            <thead><tr><th>درس</th><th class="num">صفحه</th><th class="num">زمان</th><th class="num">روز فعال</th><th class="num">سهم از صفحات</th></tr></thead>
            <tbody>
            <?php foreach ($courses as $c):
                $v = $perCourse[(int)$c['id']] ?? ['pages' => 0, 'minutes' => 0, 'days' => 0];
                if ($v['pages'] === 0 && $v['minutes'] === 0 && $c['status'] !== 'active') { continue; }
                $share = $total['pages'] > 0 ? (int)round($v['pages'] * 100 / $total['pages']) : 0; ?>
                <tr>
                    <td><span class="dot" style="background:<?= e($c['color']) ?>"></span> <?= e($c['title']) ?></td>
                    <td class="num"><?= e(fnum($v['pages'])) ?></td>
                    <td class="num"><?= e(fmt_minutes($v['minutes'])) ?></td>
                    <td class="num"><?= e(fnum($v['days'])) ?></td>
                    <td class="num"><?= e(fnum($share)) ?>٪</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr>
                <td>جمع</td><td class="num"><?= e(fnum($total['pages'])) ?></td>
                <td class="num"><?= e(fmt_minutes($total['minutes'])) ?></td>
                <td class="num"><?= e(fnum($total['days'])) ?></td><td></td>
            </tr></tfoot>
        </table>
        <?php endif; ?>
    </div>
</div>

<?php if ($p === 'day'): ?>
<div class="postbox box-static" style="margin-top:20px">
    <div class="postbox-head"><h2>مطالعه‌های ثبت‌شده در این روز</h2></div>
    <div class="postbox-body">
    <?php if (!$dayLogs): ?>
        <div class="empty">برای این روز چیزی ثبت نشده است.</div>
    <?php else: ?>
        <table class="list">
            <thead><tr><th>درس</th><th class="num">صفحه</th><th class="num">زمان</th><th>یادداشت</th></tr></thead>
            <tbody>
            <?php foreach ($dayLogs as $l): ?>
                <tr><td><span class="dot" style="background:<?= e($l['color']) ?>"></span> <?= e($l['title']) ?></td>
                    <td class="num"><?= e(fnum($l['pages'])) ?></td>
                    <td class="num"><?= e(fmt_minutes((int)$l['minutes'])) ?></td>
                    <td><?= e($l['note']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="postbox box-static" style="margin-top:20px">
    <div class="postbox-head"><h2><?= $p === 'year' ? 'ریز ماه‌ها' : 'ریز روزها' ?></h2></div>
    <div class="postbox-body">
        <div class="table-scroll">
        <table class="list">
            <thead><tr><th><?= $p === 'year' ? 'ماه' : 'روز' ?></th><th class="num">صفحه</th><th class="num">زمان</th></tr></thead>
            <tbody>
            <?php foreach ($sub as $i => $b):
                $pg = 0; $mn = 0;
                foreach ($subSeries[$i] as $v) { $pg += $v['pages']; $mn += $v['minutes']; }
                $future = $b['from'] > $today;
                $isToday = $p !== 'year' && $b['from'] === $today; ?>
                <tr<?= $isToday ? ' class="today"' : '' ?><?= $future ? ' class="off"' : '' ?>>
                    <td><?= e($b['label']) ?></td>
                    <td class="num"><?= $future ? '—' : e(fnum($pg)) ?></td>
                    <td class="num"><?= $future ? '—' : e(fmt_minutes($mn)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>
<?php endif; ?>
<?php admin_footer();
