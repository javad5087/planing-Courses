<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_login();

$today = today_ymd();
$off = off_days();

if (is_post()) {
    csrf_check();
    $action = post_str('action', 20);
    $id = post_int('id');

    if ($action === 'delete' && $id > 0) {
        db()->prepare('DELETE FROM courses WHERE id = ?')->execute([$id]);
        flash('success', 'درس و تمام مطالعه‌های ثبت‌شدهٔ آن حذف شد.');
    } elseif ($action === 'archive' && $id > 0) {
        db()->prepare('UPDATE courses SET status = \'archived\' WHERE id = ?')->execute([$id]);
        flash('success', 'درس بایگانی شد (از برنامه و پیشخوان حذف می‌شود، آمار آن می‌ماند).');
    } elseif ($action === 'activate' && $id > 0) {
        db()->prepare('UPDATE courses SET status = \'active\' WHERE id = ?')->execute([$id]);
        flash('success', 'درس دوباره فعال شد.');
    } elseif ($action === 'seed') {
        if ((int)db()->query('SELECT COUNT(*) FROM courses')->fetchColumn() === 0) {
            $start = $today;
            $end = ymd_add_days(j_add_months($start, plan_months_default()), -1);
            $ins = db()->prepare('INSERT INTO courses(title, start_page, total_pages, daily_minutes, plan_start, plan_end, color, sort_order)
                VALUES(?,?,?,?,?,?,?,?)');
            $pal = course_palette();
            foreach (['جزا', 'آ.د.ک', 'جرم‌شناسی', 'متون فقه'] as $i => $t) {
                $ins->execute([$t, 1, 0, 60, $start, $end, $pal[$i], $i]);
            }
            flash('success', 'چهار درس پیش‌فرض اضافه شد. برای هر کدام تعداد صفحات را مشخص کنید.');
        }
    }
    redirect(url('admin/courses.php'));
}

$courses = courses_all(false);
$active = array_filter($courses, fn($c) => $c['status'] === 'active');
$archived = array_filter($courses, fn($c) => $c['status'] !== 'active');

function course_card(array $c, string $today, array $off): void
{
    $s = plan_status($c, $today, $off);
    $id = (int)$c['id'];
    ?>
    <div class="postbox" data-box="course-<?= $id ?>"<?= $s['today_target'] > 0 ? ' data-open' : '' ?>>
        <div class="postbox-head">
            <div class="head-title">
                <span class="dot" style="background:<?= e($c['color']) ?>"></span>
                <strong><?= e($c['title']) ?></strong>
            </div>
            <span class="head-meta">
                <?php if (!$s['configured']): ?>
                    بدون صفحه
                <?php else: ?>
                    <?= e(fnum($s['percent'])) ?>٪
                <?php endif; ?>
            </span>
            <button type="button" class="toggle" aria-label="باز و بسته کردن" aria-expanded="false"></button>
        </div>
        <div class="mini-progress"><?= progress_bar($s['percent'], $c['color']) ?></div>
        <div class="postbox-body">
        <?php if (!$s['configured']): ?>
            <p><span class="badge badge-warn">تعداد صفحات هنوز تعیین نشده</span></p>
            <p class="desc">برای محاسبهٔ برنامهٔ روزانه، تعداد صفحات این درس را از «ویرایش» وارد کنید.</p>
        <?php else: ?>
            <dl class="kv">
                <dt>صفحات این درس</dt>
                <dd><?= e(fnum($c['total_pages'])) ?> صفحه (از صفحهٔ <?= e(fnum($c['start_page'])) ?>)</dd>
                <dt>خوانده‌شده</dt>
                <dd><?= e(fnum($s['done'])) ?> صفحه · مانده: <?= e(fnum($s['remaining'])) ?></dd>
                <dt>بازهٔ برنامه</dt>
                <dd><?= e(jfmt($c['plan_start'], 'long')) ?> تا <?= e(jfmt($c['plan_end'], 'long')) ?></dd>
                <?php if ($s['finished']): ?>
                    <dt>وضعیت</dt><dd><span class="badge badge-ok">تمام شد 🎉</span></dd>
                <?php else: ?>
                    <dt>روزهای مطالعهٔ باقی‌مانده</dt><dd><?= e(fnum($s['days_left'])) ?> روز</dd>
                    <dt>روزی</dt>
                    <dd><?php if ($s['overdue']): ?><span class="badge badge-bad">مهلت گذشته</span>
                        <?php else: ?><?= e(fnum($s['per_day'])) ?> صفحه (هفته‌ای <?= e(fnum($s['per_week'])) ?>)<?php endif; ?></dd>
                    <dt>صفحهٔ بعدی</dt><dd><?= e(fnum($s['next_page'])) ?></dd>
                <?php endif; ?>
                <dt>زمان مطالعه</dt>
                <dd><?= e(fmt_minutes((int)$c['done_minutes'])) ?>
                    <?php if ((int)$c['daily_minutes'] > 0): ?>
                        <span class="desc">· هدف روزانه: <?= e(fmt_minutes((int)$c['daily_minutes'])) ?></span>
                    <?php endif; ?></dd>
            </dl>
        <?php endif; ?>

        <?php if ($c['status'] === 'active'): ?>
            <form method="post" action="<?= e(url('admin/log.php')) ?>" class="field-row" style="margin:10px 0 14px">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="course_id" value="<?= $id ?>">
                <input type="hidden" name="log_date" value="<?= e($today) ?>">
                <input type="hidden" name="back" value="courses">
                <div class="field"><label for="p<?= $id ?>">صفحه</label>
                    <input type="text" id="p<?= $id ?>" name="pages" class="small-text fa-num" inputmode="numeric" value="<?= e(fa($s['today_target'] > $s['today_done'] ? $s['today_target'] - $s['today_done'] : 0)) ?>"></div>
                <div class="field"><label for="m<?= $id ?>">دقیقه</label>
                    <input type="text" id="m<?= $id ?>" name="minutes" class="small-text fa-num" inputmode="numeric" value="<?= e(fa((int)$c['daily_minutes'])) ?>"></div>
                <button type="submit" class="button button-primary">ثبت مطالعهٔ امروز</button>
            </form>
        <?php endif; ?>

        <?php if (trim($c['notes']) !== ''): ?>
            <p class="desc" style="white-space:pre-line"><?= e($c['notes']) ?></p>
        <?php endif; ?>

        <div class="actions">
            <a class="button" href="<?= e(url('admin/course-edit.php?id=' . $id)) ?>">ویرایش</a>
            <a class="button" href="<?= e(url('admin/plan.php?course=' . $id)) ?>">برنامهٔ روزانه</a>
            <form method="post" style="display:inline">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
                <?php if ($c['status'] === 'active'): ?>
                    <button class="button" name="action" value="archive" type="submit">بایگانی</button>
                <?php else: ?>
                    <button class="button" name="action" value="activate" type="submit">فعال‌سازی</button>
                <?php endif; ?>
            </form>
            <form method="post" style="display:inline" data-confirm-submit="این درس همراه با همهٔ مطالعه‌های ثبت‌شده‌اش برای همیشه حذف شود؟">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
                <button class="button button-danger" name="action" value="delete" type="submit">حذف</button>
            </form>
        </div>
        </div>
    </div>
    <?php
}

admin_header('دروس', 'افزودن درس', url('admin/course-edit.php'));
?>

<?php if (!$courses): ?>
    <div class="postbox"><div class="postbox-body empty">
        هنوز درسی ندارید.<br><br>
        <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <button class="button button-primary" name="action" value="seed" type="submit">افزودن ۴ درس: جزا، آ.د.ک، جرم‌شناسی، متون فقه</button>
        </form>
        <a class="button" href="<?= e(url('admin/course-edit.php')) ?>">افزودن درس دلخواه</a>
    </div></div>
<?php else: ?>
    <p class="actions">
        <button type="button" class="button button-small" data-box-all="open">باز کردن همه</button>
        <button type="button" class="button button-small" data-box-all="close">بستن همه</button>
    </p>
    <div class="grid grid-cards">
        <?php foreach ($active as $c) course_card($c, $today, $off); ?>
    </div>

    <?php if ($archived): ?>
        <h2 style="margin:28px 0 12px;font-size:16px">دروس بایگانی‌شده</h2>
        <div class="grid grid-cards">
            <?php foreach ($archived as $c) course_card($c, $today, $off); ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php admin_footer();
