<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_login();

$today = today_ymd();
$off = off_days();
$editId = get_int('id');
$entry = null;
if ($editId > 0) {
    $st = db()->prepare('SELECT * FROM study_logs WHERE id = ?');
    $st->execute([$editId]);
    $entry = $st->fetch() ?: null;
    if (!$entry) {
        flash('error', 'رکورد مطالعه پیدا نشد.');
        redirect(url('admin/history.php'));
    }
}

if (is_post()) {
    csrf_check();
    $action = post_str('action', 10);
    $courseId = post_int('course_id');
    $date = (string)($_POST['log_date'] ?? '');
    $pages = post_int('pages', 0);
    $minutes = post_int('minutes', 0);
    $note = post_str('note', 500);
    $back = post_str('back', 12) === 'courses' ? url('admin/courses.php') : url('admin/log.php');

    $errors = [];
    if (!course_get($courseId)) {
        $errors[] = 'درس معتبر انتخاب کنید.';
    }
    if (!valid_ymd($date)) {
        $errors[] = 'تاریخ نامعتبر است.';
    } elseif ($date > $today) {
        $errors[] = 'نمی‌توانید برای آینده مطالعه ثبت کنید.';
    }
    if ($pages < 0 || $minutes < 0 || $pages > 5000 || $minutes > 1440) {
        $errors[] = 'مقدار صفحه (حداکثر ۵۰۰۰) یا دقیقه (حداکثر ۱۴۴۰) نامعتبر است.';
    }
    if ($pages === 0 && $minutes === 0) {
        $errors[] = 'حداقل یکی از «صفحه» یا «دقیقه» باید بیشتر از صفر باشد.';
    }

    if (!$errors) {
        if ($action === 'update') {
            $id = post_int('id');
            db()->prepare('UPDATE study_logs SET course_id=?, log_date=?, pages=?, minutes=?, note=? WHERE id=?')
                ->execute([$courseId, $date, $pages, $minutes, $note, $id]);
            flash('success', 'مطالعه به‌روز شد.');
            redirect(url('admin/history.php'));
        }
        db()->prepare('INSERT INTO study_logs(course_id, log_date, pages, minutes, note) VALUES(?,?,?,?,?)')
            ->execute([$courseId, $date, $pages, $minutes, $note]);
        flash('success', 'مطالعه ثبت شد.');
        redirect($back);
    }
    foreach ($errors as $er) {
        flash('error', $er);
    }
    // برای نمایش مجدد فرم با مقادیر وارد‌شده
    $entry = ['id' => post_int('id'), 'course_id' => $courseId, 'log_date' => valid_ymd($date) ? $date : $today,
        'pages' => $pages, 'minutes' => $minutes, 'note' => $note];
    $editId = $action === 'update' ? (int)$entry['id'] : 0;
}

$courses = courses_all(true);
$selCourse = $entry ? (int)$entry['course_id'] : get_int('course');
$status = [];
foreach ($courses as $c) {
    $status[(int)$c['id']] = plan_status($c, $today, $off);
}

$todayLogs = db()->prepare('SELECT l.*, c.title, c.color FROM study_logs l JOIN courses c ON c.id = l.course_id
    WHERE l.log_date = ? ORDER BY l.id DESC');
$todayLogs->execute([$today]);
$todayLogs = $todayLogs->fetchAll();

admin_header($editId ? 'ویرایش مطالعه' : 'ثبت مطالعهٔ جدید');
?>
<?php if (!$courses): ?>
    <div class="postbox"><div class="postbox-body empty">ابتدا یک درس فعال اضافه کنید.
        <br><br><a class="button button-primary" href="<?= e(url('admin/courses.php')) ?>">رفتن به دروس</a></div></div>
<?php else: ?>
<div class="grid grid-2">
    <div class="postbox box-static">
        <div class="postbox-head"><h2><?= $editId ? 'ویرایش رکورد' : 'ثبت مطالعه' ?></h2></div>
        <div class="postbox-body">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= $editId ? 'update' : 'add' ?>">
                <?php if ($editId): ?><input type="hidden" name="id" value="<?= (int)$editId ?>"><?php endif; ?>
                <table class="form-table">
                    <tr><th><label for="course_id">درس</label></th>
                        <td><select id="course_id" name="course_id" required>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= (int)$c['id'] ?>"<?= $selCourse === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['title']) ?></option>
                            <?php endforeach; ?>
                            <?php
                            // رکوردی که به درس بایگانی‌شده تعلق دارد هم قابل ویرایش باشد
                            if ($entry && !isset($status[(int)$entry['course_id']]) && ($ac = course_get((int)$entry['course_id']))): ?>
                                <option value="<?= (int)$ac['id'] ?>" selected><?= e($ac['title']) ?> (بایگانی)</option>
                            <?php endif; ?>
                        </select></td></tr>
                    <tr><th>تاریخ</th>
                        <td><?= jdate_field('log_date', $entry['log_date'] ?? $today) ?></td></tr>
                    <tr><th><label for="pages">صفحات خوانده‌شده</label></th>
                        <td><input type="text" id="pages" name="pages" class="small-text fa-num" inputmode="numeric"
                                   value="<?= e(fa($entry['pages'] ?? '')) ?>" placeholder="۰"></td></tr>
                    <tr><th><label for="minutes">زمان مطالعه (دقیقه)</label></th>
                        <td><input type="text" id="minutes" name="minutes" class="small-text fa-num" inputmode="numeric"
                                   value="<?= e(fa($entry['minutes'] ?? '')) ?>" placeholder="۰">
                            <p class="desc">مثلاً ۹۰ برابر یک ساعت و نیم.</p></td></tr>
                    <tr><th><label for="note">یادداشت</label></th>
                        <td><input type="text" id="note" name="note" class="regular-text" value="<?= e($entry['note'] ?? '') ?>"></td></tr>
                </table>
                <div class="actions">
                    <button type="submit" class="button button-primary"><?= $editId ? 'به‌روزرسانی' : 'ثبت' ?></button>
                    <?php if ($editId): ?><a class="button" href="<?= e(url('admin/history.php')) ?>">انصراف</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="postbox box-static">
        <div class="postbox-head"><h2>امروز، <?= e(jfmt($today, 'wd')) ?></h2></div>
        <div class="postbox-body">
            <table class="list">
                <thead><tr><th>درس</th><th class="num">هدف</th><th class="num">خوانده‌شده</th></tr></thead>
                <tbody>
                <?php foreach ($courses as $c): $s = $status[(int)$c['id']]; ?>
                    <tr>
                        <td><span class="dot" style="background:<?= e($c['color']) ?>"></span> <?= e($c['title']) ?></td>
                        <td class="num"><?= $s['configured'] && !$s['finished'] ? e(fnum($s['today_target'])) . ' صفحه' : '—' ?></td>
                        <td class="num"><?= e(fnum($s['today_done'])) ?> صفحه · <?= e(fmt_minutes($s['today_minutes'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php if ($todayLogs): ?>
                <h3 style="margin:16px 0 8px;font-size:13px">ثبت‌های امروز</h3>
                <table class="list">
                    <tbody>
                    <?php foreach ($todayLogs as $l): ?>
                        <tr>
                            <td><span class="dot" style="background:<?= e($l['color']) ?>"></span> <?= e($l['title']) ?></td>
                            <td class="num"><?= e(fnum($l['pages'])) ?> صفحه</td>
                            <td class="num"><?= e(fmt_minutes((int)$l['minutes'])) ?></td>
                            <td class="num"><a href="<?= e(url('admin/log.php?id=' . (int)$l['id'])) ?>">ویرایش</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php admin_footer();
