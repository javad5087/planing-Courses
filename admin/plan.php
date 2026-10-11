<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_login();

$today = today_ymd();
$off = off_days();
$courses = courses_all(true);
$focus = get_int('course');

admin_header('برنامهٔ مطالعه', 'ویرایش دروس', url('admin/courses.php'));
$studyPerWeek = 7 - count($off);
?>
<p class="desc" style="margin-top:0">
    صفحات هر درس به‌طور مساوی بین روزهای مطالعهٔ بازهٔ آن (<?= e(fnum($studyPerWeek)) ?> روز در هفته) تقسیم می‌شود.
    عدد «روزی ...» پایین‌تر بر اساس صفحات باقی‌مانده و روزهای باقی‌مانده دوباره محاسبه می‌شود، پس اگر عقب بیفتید خودکار بیشتر می‌شود.
    روزهای استراحت را از <a href="<?= e(url('admin/settings.php?tab=plan')) ?>">تنظیمات برنامه‌ریزی</a> تعیین کنید.
</p>

<?php if (!$courses): ?>
    <div class="postbox"><div class="postbox-body empty">درس فعالی وجود ندارد.
        <br><br><a class="button button-primary" href="<?= e(url('admin/courses.php')) ?>">رفتن به دروس</a></div></div>
<?php else: ?>

<div class="postbox box-static">
    <div class="postbox-head"><h2>خلاصهٔ برنامه</h2></div>
    <div class="postbox-body">
        <div class="table-scroll" style="max-height:none">
        <table class="list">
            <thead><tr>
                <th>درس</th><th class="num">صفحات</th><th class="num">خوانده‌شده</th><th class="num">مانده</th>
                <th class="num">روز باقی‌مانده</th><th class="num">روزی</th><th class="num">هفته‌ای</th><th class="num">زمان روزانه</th><th>بازه</th>
            </tr></thead>
            <tbody>
            <?php
            $sumPerDay = 0; $sumMin = 0;
            foreach ($courses as $c):
                $s = plan_status($c, $today, $off);
                if ($s['configured'] && !$s['finished'] && !$s['overdue']) { $sumPerDay += $s['per_day']; $sumMin += (int)$c['daily_minutes']; }
                ?>
                <tr>
                    <td><span class="dot" style="background:<?= e($c['color']) ?>"></span> <strong><?= e($c['title']) ?></strong></td>
                    <?php if (!$s['configured']): ?>
                        <td colspan="8"><span class="badge badge-warn">تعداد صفحات تعیین نشده</span>
                            <a href="<?= e(url('admin/course-edit.php?id=' . (int)$c['id'])) ?>">ویرایش</a></td>
                    <?php else: ?>
                        <td class="num"><?= e(fnum($s['total'])) ?></td>
                        <td class="num"><?= e(fnum($s['done'])) ?></td>
                        <td class="num"><?= e(fnum($s['remaining'])) ?></td>
                        <?php if ($s['finished']): ?>
                            <td colspan="5"><span class="badge badge-ok">تمام شد</span></td>
                        <?php else: ?>
                            <td class="num"><?= $s['overdue'] ? '<span class="badge badge-bad">گذشته</span>' : e(fnum($s['days_left'])) ?></td>
                            <td class="num"><?= $s['overdue'] ? '—' : e(fnum($s['per_day'])) . ' صفحه' ?></td>
                            <td class="num"><?= $s['overdue'] ? '—' : e(fnum($s['per_week'])) . ' صفحه' ?></td>
                            <td class="num"><?= (int)$c['daily_minutes'] > 0 ? e(fmt_minutes((int)$c['daily_minutes'])) : '—' ?></td>
                            <td><?= e(jfmt($c['plan_start'], 'day')) ?> تا <?= e(jfmt($c['plan_end'], 'day')) ?></td>
                        <?php endif; ?>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr>
                <td colspan="5">مجموع روزانه (همهٔ دروس)</td>
                <td class="num"><?= e(fnum($sumPerDay)) ?> صفحه</td>
                <td class="num"></td>
                <td class="num"><?= e(fmt_minutes($sumMin)) ?></td>
                <td></td>
            </tr></tfoot>
        </table>
        </div>
    </div>
</div>

<h2 style="margin:24px 0 12px;font-size:16px">جدول روزبه‌روز هر درس</h2>
<p class="actions">
    <button type="button" class="button button-small" data-box-all="open">باز کردن همه</button>
    <button type="button" class="button button-small" data-box-all="close">بستن همه</button>
</p>

<?php foreach ($courses as $c):
    $cid = (int)$c['id'];
    $sched = plan_schedule($c, $off);
    $s = plan_status($c, $today, $off);

    $st = db()->prepare('SELECT log_date, SUM(pages) AS p, SUM(minutes) AS m FROM study_logs WHERE course_id = ? GROUP BY log_date');
    $st->execute([$cid]);
    $byDay = [];
    foreach ($st as $r) {
        $byDay[$r['log_date']] = ['p' => (int)$r['p'], 'm' => (int)$r['m']];
    }

    // خلاصهٔ ماهانهٔ برنامه
    $monthly = [];
    foreach ($sched as $row) {
        [$jy, $jm] = ymd_to_j($row['date']);
        $k = $jy * 100 + $jm;
        $monthly[$k] = ($monthly[$k] ?? ['label' => JALALI_MONTHS[$jm] . ' ' . fa($jy), 'pages' => 0, 'days' => 0]);
        $monthly[$k]['pages'] += $row['pages'];
        $monthly[$k]['days']++;
    }
    ?>
    <div class="postbox" data-box="plan-<?= $cid ?>"<?= $focus === $cid ? ' data-open' : '' ?>>
        <div class="postbox-head">
            <div class="head-title"><span class="dot" style="background:<?= e($c['color']) ?>"></span><strong><?= e($c['title']) ?></strong></div>
            <span class="head-meta"><?= $sched ? e(fnum(count($sched))) . ' روز مطالعه' : 'بدون برنامه' ?></span>
            <button type="button" class="toggle" aria-label="باز و بسته کردن" aria-expanded="false"></button>
        </div>
        <div class="postbox-body">
        <?php if (!$sched): ?>
            <div class="empty">برای ساخت جدول، تعداد صفحات و بازهٔ برنامه را از ویرایش درس تعیین کنید.</div>
        <?php else: ?>
            <div class="grid grid-2" style="margin-bottom:14px">
                <div>
                    <h3 style="font-size:13px;margin-bottom:6px">تقسیم ماهانه</h3>
                    <table class="list">
                        <thead><tr><th>ماه</th><th class="num">روز مطالعه</th><th class="num">صفحه</th><th class="num">ساعت (برآورد)</th></tr></thead>
                        <tbody>
                        <?php foreach ($monthly as $m): ?>
                            <tr><td><?= e($m['label']) ?></td><td class="num"><?= e(fnum($m['days'])) ?></td>
                                <td class="num"><?= e(fnum($m['pages'])) ?></td>
                                <td class="num"><?= (int)$c['daily_minutes'] > 0 ? e(fmt_hours($m['days'] * (int)$c['daily_minutes'])) : '—' ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div>
                    <dl class="kv">
                        <dt>از صفحه</dt><dd><?= e(fnum($c['start_page'])) ?> تا <?= e(fnum($c['start_page'] + $c['total_pages'] - 1)) ?></dd>
                        <dt>میانگین روزانه</dt><dd><?= e(fnum($c['total_pages'] / count($sched), 1)) ?> صفحه</dd>
                        <dt>پیشرفت</dt><dd><?= e(fnum($s['done'])) ?> از <?= e(fnum($s['total'])) ?> (<?= e(fnum($s['percent'])) ?>٪)</dd>
                    </dl>
                    <?= progress_bar($s['percent'], $c['color']) ?>
                </div>
            </div>

            <div class="table-scroll">
            <table class="list">
                <thead><tr><th>تاریخ</th><th class="num">محدودهٔ صفحه</th><th class="num">تعداد</th><th class="num">زمان برنامه</th><th>وضعیت</th></tr></thead>
                <tbody>
                <?php foreach ($sched as $row):
                    $d = $row['date'];
                    $got = $byDay[$d]['p'] ?? 0;
                    $isToday = $d === $today;
                    ?>
                    <tr<?= $isToday ? ' class="today"' : '' ?>>
                        <td><?= e(jfmt($d, 'wd')) ?><?= $isToday ? ' <span class="badge badge-info">امروز</span>' : '' ?></td>
                        <td class="num"><?= $row['pages'] > 0 ? e(fnum($row['from'])) . ' تا ' . e(fnum($row['to'])) : '—' ?></td>
                        <td class="num"><?= e(fnum($row['pages'])) ?></td>
                        <td class="num"><?= (int)$c['daily_minutes'] > 0 ? e(fmt_minutes((int)$c['daily_minutes'])) : '—' ?></td>
                        <td>
                            <?php if ($d > $today): ?>
                                <span class="desc">در پیش</span>
                            <?php elseif ($got >= $row['pages'] && $got > 0): ?>
                                <span class="badge badge-ok">انجام شد (<?= e(fnum($got)) ?>)</span>
                            <?php elseif ($got > 0): ?>
                                <span class="badge badge-warn">ناقص (<?= e(fnum($got)) ?>)</span>
                            <?php elseif ($isToday): ?>
                                <span class="desc">هنوز ثبت نشده</span>
                            <?php else: ?>
                                <span class="badge badge-bad">انجام نشد</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
<?php endif; ?>
<?php admin_footer();
