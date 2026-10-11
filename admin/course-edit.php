<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_login();

$id = get_int('id');
$course = $id > 0 ? course_get($id) : null;
if ($id > 0 && !$course) {
    flash('error', 'درس پیدا نشد.');
    redirect(url('admin/courses.php'));
}

$months = plan_months_default();
$defStart = today_ymd();
$defEnd = ymd_add_days(j_add_months($defStart, $months), -1);

$f = $course ?: [
    'title' => '', 'start_page' => 1, 'total_pages' => '', 'daily_minutes' => 60,
    'plan_start' => $defStart, 'plan_end' => $defEnd,
    'color' => course_palette()[(int)db()->query('SELECT COUNT(*) FROM courses')->fetchColumn() % 8],
    'notes' => '',
];

if (is_post()) {
    csrf_check();
    $title = post_str('title', 120);
    $start_page = max(1, post_int('start_page', 1));
    $total = post_int('total_pages', 0);
    $dailyMin = max(0, post_int('daily_minutes', 0));
    $ps = (string)($_POST['plan_start'] ?? '');
    $pe = (string)($_POST['plan_end'] ?? '');
    $color = post_str('color', 7);
    $notes = post_str('notes', 2000);

    $f = array_merge($f, [
        'title' => $title, 'start_page' => $start_page, 'total_pages' => $total, 'daily_minutes' => $dailyMin,
        'plan_start' => $ps, 'plan_end' => $pe, 'color' => $color, 'notes' => $notes,
    ]);

    $errors = [];
    if ($title === '') {
        $errors[] = 'عنوان درس را وارد کنید.';
    }
    if ($total < 1) {
        $errors[] = 'تعداد صفحات باید حداقل ۱ باشد.';
    }
    if ($total > 100000) {
        $errors[] = 'تعداد صفحات بیش از حد بزرگ است.';
    }
    if ($dailyMin > 1440) {
        $errors[] = 'هدف مطالعهٔ روزانه نمی‌تواند بیش از ۱۴۴۰ دقیقه باشد.';
    }
    if (!valid_ymd($ps) || !valid_ymd($pe)) {
        $errors[] = 'تاریخ شروع یا پایان نامعتبر است.';
    } elseif ($pe < $ps) {
        $errors[] = 'تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد.';
    } elseif (!study_days($ps, $pe, off_days())) {
        $errors[] = 'در این بازه هیچ روز مطالعه‌ای نیست (همهٔ روزها تعطیل‌اند).';
    }
    if (!valid_color($color)) {
        $f['color'] = '#2271b1';
        $color = '#2271b1';
    }

    if (!$errors) {
        if ($course) {
            db()->prepare('UPDATE courses SET title=?, start_page=?, total_pages=?, daily_minutes=?, plan_start=?, plan_end=?, color=?, notes=? WHERE id=?')
                ->execute([$title, $start_page, $total, $dailyMin, $ps, $pe, $color, $notes, $course['id']]);
            flash('success', 'درس به‌روز شد.');
        } else {
            $order = (int)db()->query('SELECT COALESCE(MAX(sort_order),0)+1 FROM courses')->fetchColumn();
            db()->prepare('INSERT INTO courses(title, start_page, total_pages, daily_minutes, plan_start, plan_end, color, notes, sort_order)
                VALUES(?,?,?,?,?,?,?,?,?)')
                ->execute([$title, $start_page, $total, $dailyMin, $ps, $pe, $color, $notes, $order]);
            flash('success', 'درس اضافه شد.');
        }
        redirect(url('admin/courses.php'));
    }
    foreach ($errors as $er) {
        flash('error', $er);
    }
}

admin_header($course ? 'ویرایش درس' : 'افزودن درس');
?>
<form method="post">
    <?= csrf_field() ?>
    <div class="postbox box-static"><div class="postbox-body">
        <table class="form-table">
            <tr>
                <th><label for="title">عنوان درس</label></th>
                <td><input type="text" id="title" name="title" class="regular-text" value="<?= e($f['title']) ?>" required></td>
            </tr>
            <tr>
                <th><label for="total_pages">تعداد صفحات برای این درس</label></th>
                <td>
                    <input type="text" id="total_pages" name="total_pages" class="small-text fa-num" inputmode="numeric" value="<?= e($f['total_pages'] === '' ? '' : fa($f['total_pages'])) ?>" required>
                    <p class="desc">مجموع صفحاتی که می‌خواهید در بازهٔ برنامه بخوانید. این عدد را هر وقت لازم بود تغییر دهید؛ برنامه دوباره محاسبه می‌شود.</p>
                </td>
            </tr>
            <tr>
                <th><label for="start_page">شمارهٔ صفحهٔ شروع</label></th>
                <td>
                    <input type="text" id="start_page" name="start_page" class="small-text fa-num" inputmode="numeric" value="<?= e(fa($f['start_page'])) ?>">
                    <p class="desc">اگر کتاب را از صفحهٔ ۱ شروع نمی‌کنید، شماره را بنویسید تا محدودهٔ صفحات روزانه درست نمایش داده شود.</p>
                </td>
            </tr>
            <tr>
                <th>بازهٔ برنامه</th>
                <td>
                    <div class="field-row">
                        <div class="field"><label>از</label><?= jdate_field('plan_start', $f['plan_start']) ?></div>
                        <div class="field"><label>تا</label><?= jdate_field('plan_end', $f['plan_end']) ?></div>
                    </div>
                    <p class="desc">پیش‌فرض <?= e(fnum($months)) ?> ماه است؛ صفحات به‌طور مساوی بین روزهای مطالعهٔ این بازه تقسیم می‌شود.</p>
                </td>
            </tr>
            <tr>
                <th><label for="daily_minutes">هدف مطالعهٔ روزانه (دقیقه)</label></th>
                <td>
                    <input type="text" id="daily_minutes" name="daily_minutes" class="small-text fa-num" inputmode="numeric" value="<?= e(fa($f['daily_minutes'])) ?>">
                    <p class="desc">مثلاً ۹۰ برابر یک ساعت و نیم. صفر یعنی بدون هدف زمانی.</p>
                </td>
            </tr>
            <tr>
                <th><label for="color">رنگ</label></th>
                <td><input type="color" id="color" name="color" value="<?= e($f['color']) ?>"></td>
            </tr>
            <tr>
                <th><label for="notes">یادداشت</label></th>
                <td><textarea id="notes" name="notes" rows="3"><?= e($f['notes']) ?></textarea></td>
            </tr>
        </table>
        <div class="actions">
            <button type="submit" class="button button-primary"><?= $course ? 'به‌روزرسانی' : 'افزودن درس' ?></button>
            <a class="button" href="<?= e(url('admin/courses.php')) ?>">انصراف</a>
        </div>
    </div></div>
</form>
<?php admin_footer();
