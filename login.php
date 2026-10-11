<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
start_session();

if (current_user()) {
    redirect(url('admin/dashboard.php'));
}

// تا وقتی هیچ مدیری ساخته نشده، همین صفحه «ایجاد اولین مدیر» را نشان می‌دهد.
// پس از ساخت اولین مدیر، هیچ مسیر ثبت‌نام عمومی وجود ندارد.
$setupMode = users_count() === 0;
$error = '';
$old = ['username' => '', 'display_name' => ''];

if (is_post()) {
    csrf_check();

    if ($setupMode) {
        $username = post_str('username', 32);
        $display = post_str('display_name', 60);
        $pass = (string)($_POST['password'] ?? '');
        $pass2 = (string)($_POST['password2'] ?? '');
        $old = ['username' => $username, 'display_name' => $display];

        if (!valid_username($username)) {
            $error = 'نام کاربری باید ۳ تا ۳۲ نویسهٔ انگلیسی، عدد، نقطه، خط تیره یا زیرخط باشد.';
        } elseif ($display === '') {
            $error = 'نام نمایشی را وارد کنید.';
        } elseif (mb_strlen($pass) < 8) {
            $error = 'گذرواژه باید حداقل ۸ نویسه باشد.';
        } elseif ($pass !== $pass2) {
            $error = 'گذرواژه و تکرار آن یکسان نیستند.';
        } else {
            $id = create_first_admin($username, $display, $pass);
            if ($id === null) {
                $setupMode = false;
                $error = 'مدیر قبلاً ساخته شده است؛ وارد شوید.';
            } else {
                set_setting('site_title', 'برنامه مطالعه');
                set_setting('plan_months', '4');
                set_setting('off_days', '');
                login_user($id);
                flash('success', 'حساب مدیر ساخته شد. خوش آمدید!');
                redirect(url('admin/dashboard.php'));
            }
        }
    } else {
        if (login_blocked()) {
            $error = 'تلاش‌های ناموفق زیاد بود. ۱۵ دقیقه بعد دوباره امتحان کنید.';
        } else {
            $username = post_str('username', 32);
            $pass = (string)($_POST['password'] ?? '');
            $st = db()->prepare('SELECT id, password_hash FROM users WHERE username = ? COLLATE NOCASE');
            $st->execute([$username]);
            $row = $st->fetch();
            // زمان پاسخ را برای کاربر نامعتبر هم یکسان نگه می‌داریم
            $hash = $row['password_hash'] ?? '$2y$10$usesomesillystringforsaltuseless/xxxxxxxxxxxxxxxxxxxxxxxxxxxx';
            $ok = password_verify($pass, $hash) && $row;
            if ($ok) {
                if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
                    db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                        ->execute([password_hash($pass, PASSWORD_DEFAULT), $row['id']]);
                }
                login_user((int)$row['id']);
                redirect(url('admin/dashboard.php'));
            }
            login_fail();
            $error = 'نام کاربری یا گذرواژه درست نیست.';
        }
    }
}
$title = $setupMode ? 'ایجاد اولین مدیر' : 'ورود به پنل';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/rastikerdar/vazir-font@v30.1.0/dist/font-face.css">
<link rel="stylesheet" href="<?= e(url('assets/css/admin.css')) ?>?v=<?= e(APP_VERSION) ?>">
</head>
<body class="login">
<div class="login-box">
    <h1><?= $setupMode ? 'ایجاد اولین مدیر' : e(setting('site_title', 'برنامه مطالعه')) ?></h1>
    <?php if ($error): ?>
        <div class="notice notice-error"><p><?= e($error) ?></p></div>
    <?php endif; ?>
    <form method="post" autocomplete="on">
        <?= csrf_field() ?>
        <?php if ($setupMode): ?>
            <p class="desc" style="margin-top:0">این صفحه فقط یک‌بار برای ساخت مدیر اصلی نمایش داده می‌شود؛ پس از آن ثبت‌نام عمومی غیرفعال است.</p>
            <p><label for="username">نام کاربری (انگلیسی)</label>
               <input type="text" id="username" name="username" value="<?= e($old['username']) ?>" dir="ltr" required autofocus></p>
            <p><label for="display_name">نام نمایشی</label>
               <input type="text" id="display_name" name="display_name" value="<?= e($old['display_name']) ?>" required></p>
            <p><label for="password">گذرواژه (حداقل ۸ نویسه)</label>
               <input type="password" id="password" name="password" dir="ltr" autocomplete="new-password" required></p>
            <p><label for="password2">تکرار گذرواژه</label>
               <input type="password" id="password2" name="password2" dir="ltr" autocomplete="new-password" required></p>
            <p><button type="submit" class="button button-primary">ساخت حساب مدیر</button></p>
        <?php else: ?>
            <p><label for="username">نام کاربری</label>
               <input type="text" id="username" name="username" dir="ltr" required autofocus autocomplete="username"></p>
            <p><label for="password">گذرواژه</label>
               <input type="password" id="password" name="password" dir="ltr" required autocomplete="current-password"></p>
            <p><button type="submit" class="button button-primary">ورود</button></p>
        <?php endif; ?>
    </form>
    <p class="hint"><?= e(jfmt(today_ymd(), 'full')) ?></p>
</div>
</body>
</html>
