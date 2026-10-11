<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
$me = require_login();

if (is_post()) {
    csrf_check();
    $id = post_int('id');
    if (post_str('action', 10) === 'delete' && $id > 0) {
        if ($id === (int)$me['id']) {
            flash('error', 'نمی‌توانید حساب خودتان را حذف کنید.');
        } elseif (users_count() <= 1) {
            flash('error', 'آخرین مدیر قابل حذف نیست.');
        } else {
            db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            flash('success', 'کاربر حذف شد.');
        }
    }
    redirect(url('admin/users.php'));
}

$users = db()->query('SELECT id, username, display_name, role, created_at FROM users ORDER BY id')->fetchAll();

admin_header('کاربران', 'افزودن مدیر', url('admin/user-edit.php'));
?>
<div class="notice"><p>ثبت‌نام عمومی غیرفعال است؛ فقط مدیران فعلی می‌توانند مدیر جدید اضافه کنند.</p></div>
<table class="list">
    <thead><tr><th>نام کاربری</th><th>نام نمایشی</th><th>نقش</th><th>تاریخ ساخت</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
        <tr>
            <td><strong dir="ltr"><?= e($u['username']) ?></strong>
                <?= (int)$u['id'] === (int)$me['id'] ? ' <span class="badge badge-info">شما</span>' : '' ?>
                <div class="row-actions">
                    <a href="<?= e(url('admin/user-edit.php?id=' . (int)$u['id'])) ?>">ویرایش</a>
                    <?php if ((int)$u['id'] !== (int)$me['id']): ?>
                        |
                        <form method="post" data-confirm-submit="این کاربر حذف شود؟">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button type="submit" class="link-danger" name="action" value="delete">حذف</button>
                        </form>
                    <?php endif; ?>
                </div></td>
            <td><?= e($u['display_name']) ?></td>
            <td>مدیر</td>
            <td><?= e(jfmt(substr($u['created_at'], 0, 10), 'long')) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php admin_footer();
