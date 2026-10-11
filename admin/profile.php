<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
$me = require_login();

if (is_post()) {
    csrf_check();
    $display = post_str('display_name', 60);
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['password'] ?? '');
    $new2 = (string)($_POST['password2'] ?? '');

    $errors = [];
    if ($display === '') {
        $errors[] = 'نام نمایشی را وارد کنید.';
    }
    if ($new !== '') {
        $st = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$me['id']]);
        $hash = (string)$st->fetchColumn();
        if (!password_verify($cur, $hash)) {
            $errors[] = 'گذرواژهٔ فعلی درست نیست.';
        }
        if (mb_strlen($new) < 8) {
            $errors[] = 'گذرواژهٔ جدید باید حداقل ۸ نویسه باشد.';
        }
        if ($new !== $new2) {
            $errors[] = 'گذرواژهٔ جدید و تکرار آن یکسان نیستند.';
        }
    }
    if (!$errors) {
        db()->prepare('UPDATE users SET display_name = ? WHERE id = ?')->execute([$display, $me['id']]);
        if ($new !== '') {
            db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
        }
        flash('success', 'پروفایل به‌روز شد.');
        redirect(url('admin/profile.php'));
    }
    foreach ($errors as $er) {
        flash('error', $er);
    }
    $me['display_name'] = $display;
}

admin_header('پروفایل من');
?>
<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div class="postbox box-static"><div class="postbox-body">
        <table class="form-table">
            <tr><th>نام کاربری</th><td><strong dir="ltr"><?= e($me['username']) ?></strong></td></tr>
            <tr><th><label for="display_name">نام نمایشی</label></th>
                <td><input type="text" id="display_name" name="display_name" class="regular-text" value="<?= e($me['display_name']) ?>" required></td></tr>
            <tr><th><label for="current">گذرواژهٔ فعلی</label></th>
                <td><input type="password" id="current" name="current" class="regular-text" dir="ltr" autocomplete="current-password">
                    <p class="desc">فقط هنگام تغییر گذرواژه لازم است.</p></td></tr>
            <tr><th><label for="password">گذرواژهٔ جدید</label></th>
                <td><input type="password" id="password" name="password" class="regular-text" dir="ltr" autocomplete="new-password"></td></tr>
            <tr><th><label for="password2">تکرار گذرواژهٔ جدید</label></th>
                <td><input type="password" id="password2" name="password2" class="regular-text" dir="ltr" autocomplete="new-password"></td></tr>
        </table>
        <button type="submit" class="button button-primary">ذخیره</button>
    </div></div>
</form>
<?php admin_footer();
