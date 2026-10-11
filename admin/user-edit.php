<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
$me = require_login();

$id = get_int('id');
$user = null;
if ($id > 0) {
    $st = db()->prepare('SELECT id, username, display_name FROM users WHERE id = ?');
    $st->execute([$id]);
    $user = $st->fetch() ?: null;
    if (!$user) {
        flash('error', 'کاربر پیدا نشد.');
        redirect(url('admin/users.php'));
    }
}
$f = $user ?: ['username' => '', 'display_name' => ''];

if (is_post()) {
    csrf_check();
    $username = post_str('username', 32);
    $display = post_str('display_name', 60);
    $pass = (string)($_POST['password'] ?? '');
    $f = ['username' => $username, 'display_name' => $display];

    $errors = [];
    if (!valid_username($username)) {
        $errors[] = 'نام کاربری باید ۳ تا ۳۲ نویسهٔ انگلیسی، عدد، نقطه، خط تیره یا زیرخط باشد.';
    }
    if ($display === '') {
        $errors[] = 'نام نمایشی را وارد کنید.';
    }
    if (!$user && mb_strlen($pass) < 8) {
        $errors[] = 'گذرواژه باید حداقل ۸ نویسه باشد.';
    }
    if ($user && $pass !== '' && mb_strlen($pass) < 8) {
        $errors[] = 'گذرواژهٔ جدید باید حداقل ۸ نویسه باشد.';
    }
    if (!$errors) {
        $st = db()->prepare('SELECT id FROM users WHERE username = ? COLLATE NOCASE AND id != ?');
        $st->execute([$username, $user['id'] ?? 0]);
        if ($st->fetch()) {
            $errors[] = 'این نام کاربری قبلاً استفاده شده است.';
        }
    }
    if (!$errors) {
        if ($user) {
            db()->prepare('UPDATE users SET username = ?, display_name = ? WHERE id = ?')
                ->execute([$username, $display, $user['id']]);
            if ($pass !== '') {
                db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                    ->execute([password_hash($pass, PASSWORD_DEFAULT), $user['id']]);
            }
            flash('success', 'کاربر به‌روز شد.');
        } else {
            db()->prepare('INSERT INTO users(username, display_name, password_hash, role) VALUES(?,?,?,\'admin\')')
                ->execute([$username, $display, password_hash($pass, PASSWORD_DEFAULT)]);
            flash('success', 'مدیر جدید اضافه شد.');
        }
        redirect(url('admin/users.php'));
    }
    foreach ($errors as $er) {
        flash('error', $er);
    }
}

admin_header($user ? 'ویرایش کاربر' : 'افزودن مدیر');
?>
<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <div class="postbox box-static"><div class="postbox-body">
        <table class="form-table">
            <tr><th><label for="username">نام کاربری</label></th>
                <td><input type="text" id="username" name="username" class="regular-text" dir="ltr" value="<?= e($f['username']) ?>" required></td></tr>
            <tr><th><label for="display_name">نام نمایشی</label></th>
                <td><input type="text" id="display_name" name="display_name" class="regular-text" value="<?= e($f['display_name']) ?>" required></td></tr>
            <tr><th><label for="password"><?= $user ? 'گذرواژهٔ جدید' : 'گذرواژه' ?></label></th>
                <td><input type="password" id="password" name="password" class="regular-text" dir="ltr" autocomplete="new-password"<?= $user ? '' : ' required' ?>>
                    <p class="desc"><?= $user ? 'برای تغییر ندادن، خالی بگذارید. ' : '' ?>حداقل ۸ نویسه.</p></td></tr>
        </table>
        <div class="actions">
            <button type="submit" class="button button-primary"><?= $user ? 'به‌روزرسانی' : 'افزودن مدیر' ?></button>
            <a class="button" href="<?= e(url('admin/users.php')) ?>">انصراف</a>
        </div>
    </div></div>
</form>
<?php admin_footer();
