<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_login();

$tab = (string)($_GET['tab'] ?? 'general');
if (!in_array($tab, ['general', 'plan'], true)) {
    $tab = 'general';
}

if (is_post()) {
    csrf_check();
    $which = post_str('tab', 10);
    if ($which === 'general') {
        $title = post_str('site_title', 80);
        set_setting('site_title', $title !== '' ? $title : 'برنامه مطالعه');
        flash('success', 'تنظیمات ذخیره شد.');
        redirect(url('admin/settings.php?tab=general'));
    }
    if ($which === 'plan') {
        $months = post_int('plan_months', 4);
        $months = max(1, min(24, $months));
        $offs = array_values(array_unique(array_filter(
            array_map('intval', (array)($_POST['off_days'] ?? [])),
            fn($v) => $v >= 0 && $v <= 6
        )));
        if (count($offs) >= 7) {
            flash('error', 'حداقل یک روز هفته باید روز مطالعه باشد.');
            redirect(url('admin/settings.php?tab=plan'));
        }
        sort($offs);
        set_setting('plan_months', (string)$months);
        set_setting('off_days', implode(',', $offs));
        flash('success', 'تنظیمات برنامه‌ریزی ذخیره شد. این تغییر برنامهٔ همهٔ دروس را دوباره محاسبه می‌کند؛ بازهٔ دروس موجود تغییر نمی‌کند.');
        redirect(url('admin/settings.php?tab=plan'));
    }
}

admin_header('تنظیمات');
$off = off_days();
?>
<div class="tabs">
    <a href="<?= e(url('admin/settings.php?tab=general')) ?>" class="<?= $tab === 'general' ? 'active' : '' ?>">عمومی</a>
    <a href="<?= e(url('admin/settings.php?tab=plan')) ?>" class="<?= $tab === 'plan' ? 'active' : '' ?>">برنامه‌ریزی</a>
</div>

<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="postbox box-static"><div class="postbox-body">
    <?php if ($tab === 'general'): ?>
        <table class="form-table">
            <tr><th><label for="site_title">عنوان سایت</label></th>
                <td><input type="text" id="site_title" name="site_title" class="regular-text" value="<?= e(setting('site_title', 'برنامه مطالعه')) ?>"></td></tr>
        </table>
    <?php else: ?>
        <table class="form-table">
            <tr><th><label for="plan_months">طول پیش‌فرض برنامه (ماه)</label></th>
                <td><input type="text" id="plan_months" name="plan_months" class="small-text fa-num" inputmode="numeric" value="<?= e(fa(plan_months_default())) ?>">
                    <p class="desc">برای درس‌های جدید، تاریخ پایان به‌صورت پیش‌فرض این‌قدر ماه شمسی بعد از شروع تنظیم می‌شود (پیش‌فرض ۴).</p></td></tr>
            <tr><th>روزهای استراحت</th>
                <td class="checks">
                    <?php foreach (JALALI_WEEKDAYS as $i => $name): ?>
                        <label><input type="checkbox" name="off_days[]" value="<?= $i ?>"<?= in_array($i, $off, true) ? ' checked' : '' ?>> <?= e($name) ?></label>
                    <?php endforeach; ?>
                    <p class="desc">در روزهای انتخاب‌شده صفحه‌ای برنامه‌ریزی نمی‌شود (مثلاً جمعه).</p></td></tr>
        </table>
    <?php endif; ?>
        <button type="submit" class="button button-primary">ذخیرهٔ تغییرات</button>
    </div></div>
</form>
<?php admin_footer();
