<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_login();

$today = today_ymd();
$off = off_days();
$courses = courses_all(true);

$periods = [
    ['امروز', period_info('day', $today)],
    ['این هفته', period_info('week', $today)],
    ['این ماه', period_info('month', $today)],
    ['امسال', period_info('year', $today)],
];
$streak = study_streak($today);

$buckets = trailing_buckets('day', $today, 7);
$series = bucket_series($buckets);

$recent = db()->query('SELECT l.*, c.title, c.color FROM study_logs l JOIN courses c ON c.id = l.course_id
    ORDER BY l.log_date DESC, l.id DESC LIMIT 8')->fetchAll();

admin_header('پیشخوان');
?>

<?php if (!$courses): ?>
<div class="postbox"><div class="postbox-body empty">
    هنوز درسی تعریف نشده است.<br><br>
    <a class="button button-primary" href="<?= e(url('admin/course-edit.php')) ?>">افزودن اولین درس</a>
    <a class="button" href="<?= e(url('admin/courses.php')) ?>">افزودن دروس پیش‌فرض</a>
</div></div>
<?php endif; ?>

<div class="grid grid-4" style="margin-bottom:20px">
    <?php foreach ($periods as [$lbl, $info]):
        $t = stats_total($info['from'], $info['to']); ?>
        <div class="stat-card">
            <div class="lbl"><?= e($lbl) ?></div>
            <div class="num"><?= e(fnum($t['pages'])) ?> <small style="font-size:13px;font-weight:400">صفحه</small></div>
            <div class="sub"><?= e(fmt_minutes($t['minutes'])) ?> مطالعه · <?= e(fnum($t['days'])) ?> روز فعال</div>
        </div>
    <?php endforeach; ?>
</div>

<div class="grid grid-2">
    <div class="postbox box-static">
        <div class="postbox-head"><h2>برنامهٔ امروز — <?= e(jfmt($today, 'full')) ?></h2></div>
        <div class="postbox-body">
        <?php if (!$courses): ?>
            <div class="empty">درسی برای نمایش نیست.</div>
        <?php else: ?>
            <table class="list">
                <thead><tr><th>درس</th><th class="num">هدف امروز</th><th class="num">خوانده‌شده</th><th class="num">زمان</th></tr></thead>
                <tbody>
                <?php foreach ($courses as $c):
                    $s = plan_status($c, $today, $off); ?>
                    <tr>
                        <td><span class="dot" style="background:<?= e($c['color']) ?>"></span>
                            <a href="<?= e(url('admin/log.php?course=' . (int)$c['id'])) ?>"><?= e($c['title']) ?></a></td>
                        <?php if (!$s['configured']): ?>
                            <td colspan="3"><span class="badge badge-warn">تعداد صفحات تعیین نشده</span></td>
                        <?php elseif ($s['finished']): ?>
                            <td colspan="3"><span class="badge badge-ok">تمام شد</span></td>
                        <?php else: ?>
                            <td class="num">
                                <?php if ($s['not_started']): ?>
                                    <span class="badge">شروع: <?= e(jfmt($c['plan_start'], 'day')) ?></span>
                                <?php elseif ($s['overdue']): ?>
                                    <span class="badge badge-bad">از برنامه عقب است</span>
                                <?php elseif (!$s['today_is_study_day']): ?>
                                    <span class="badge">روز استراحت</span>
                                <?php else: ?>
                                    <?= e(fnum($s['today_target'])) ?> صفحه
                                <?php endif; ?>
                            </td>
                            <td class="num"><?= e(fnum($s['today_done'])) ?> صفحه</td>
                            <td class="num"><?= e(fmt_minutes($s['today_minutes'])) ?>
                                <?php if ((int)$c['daily_minutes'] > 0): ?>
                                    <div class="desc">از <?= e(fmt_minutes((int)$c['daily_minutes'])) ?></div>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="desc">روزهای متوالی مطالعه: <strong><?= e(fnum($streak)) ?></strong></p>
        <?php endif; ?>
        </div>
    </div>

    <div class="postbox box-static">
        <div class="postbox-head"><h2>صفحات خوانده‌شده در ۷ روز اخیر</h2></div>
        <div class="postbox-body">
            <?= svg_stacked_bars($buckets, $series, courses_all(false), 'pages') ?>
            <div class="legend">
                <?php foreach ($courses as $c): ?>
                    <span><i class="dot" style="background:<?= e($c['color']) ?>"></i><?= e($c['title']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-2" style="margin-top:20px">
    <div class="postbox box-static">
        <div class="postbox-head"><h2>پیشرفت کلی دروس</h2></div>
        <div class="postbox-body">
        <?php if (!$courses): ?><div class="empty">—</div><?php endif; ?>
        <?php foreach ($courses as $c):
            $s = plan_status($c, $today, $off); ?>
            <div style="margin-bottom:14px">
                <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                    <strong><?= e($c['title']) ?></strong>
                    <span class="desc"><?= e(fnum($s['done'])) ?> از <?= e(fnum($s['total'])) ?> صفحه · <?= e(fnum($s['percent'])) ?>٪</span>
                </div>
                <?= progress_bar($s['percent'], $c['color']) ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>

    <div class="postbox box-static">
        <div class="postbox-head"><h2>آخرین مطالعه‌ها</h2><a href="<?= e(url('admin/history.php')) ?>">همه</a></div>
        <div class="postbox-body">
        <?php if (!$recent): ?>
            <div class="empty">هنوز مطالعه‌ای ثبت نشده. <a href="<?= e(url('admin/log.php')) ?>">ثبت اولین مطالعه</a></div>
        <?php else: ?>
            <table class="list">
                <tbody>
                <?php foreach ($recent as $r): ?>
                    <tr>
                        <td><?= e(jfmt($r['log_date'], 'wd')) ?></td>
                        <td><span class="dot" style="background:<?= e($r['color']) ?>"></span> <?= e($r['title']) ?></td>
                        <td class="num"><?= e(fnum($r['pages'])) ?> صفحه</td>
                        <td class="num"><?= e(fmt_minutes((int)$r['minutes'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        </div>
    </div>
</div>

<?php admin_footer();
