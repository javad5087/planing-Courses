<?php
declare(strict_types=1);
require __DIR__ . '/../config.php';
require_login();

if (is_post()) {
    csrf_check();
    $ids = array_filter(array_map('intval', (array)($_POST['ids'] ?? [])), fn($v) => $v > 0);
    $one = post_int('delete_one');
    if ($one > 0) {
        $ids = [$one];
    } elseif (post_str('action', 12) !== 'bulk-delete') {
        $ids = [];
    }
    if ($ids) {
        $ids = array_values($ids);
        $in = implode(',', array_fill(0, count($ids), '?'));
        db()->prepare("DELETE FROM study_logs WHERE id IN ($in)")->execute($ids);
        flash('success', fa(count($ids)) . ' رکورد حذف شد.');
    }
    $qs = ltrim((string)($_POST['qs'] ?? ''), '?');
    redirect(url('admin/history.php') . ($qs !== '' ? '?' . $qs : ''));
}

$per = 30;
$page = max(1, get_int('pg', 1));
$courseF = get_int('course');
$useRange = isset($_GET['range']) && $_GET['range'] === '1';
$fromF = (string)($_GET['from'] ?? '');
$toF = (string)($_GET['to'] ?? '');
if (!valid_ymd($fromF)) {
    $fromF = ymd_add_days(today_ymd(), -30);
}
if (!valid_ymd($toF)) {
    $toF = today_ymd();
}

$where = [];
$args = [];
if ($courseF > 0) {
    $where[] = 'l.course_id = ?';
    $args[] = $courseF;
}
if ($useRange) {
    $where[] = 'l.log_date BETWEEN ? AND ?';
    $args[] = min($fromF, $toF);
    $args[] = max($fromF, $toF);
}
$wsql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$st = db()->prepare('SELECT COUNT(*), COALESCE(SUM(pages),0), COALESCE(SUM(minutes),0) FROM study_logs l' . $wsql);
$st->execute($args);
[$count, $sumPages, $sumMin] = array_map('intval', $st->fetch(PDO::FETCH_NUM));
$pageCount = max(1, (int)ceil($count / $per));
$page = min($page, $pageCount);

$st = db()->prepare('SELECT l.*, c.title, c.color FROM study_logs l JOIN courses c ON c.id = l.course_id'
    . $wsql . ' ORDER BY l.log_date DESC, l.id DESC LIMIT ' . $per . ' OFFSET ' . (($page - 1) * $per));
$st->execute($args);
$rows = $st->fetchAll();
$courses = courses_all(false);

$baseQ = [];
if ($courseF > 0) {
    $baseQ['course'] = $courseF;
}
if ($useRange) {
    $baseQ += ['range' => 1, 'from' => $fromF, 'to' => $toF];
}
$curQ = $baseQ + ($page > 1 ? ['pg' => $page] : []);

admin_header('تاریخچهٔ مطالعه', 'ثبت مطالعهٔ جدید', url('admin/log.php'));
?>
<form method="get" class="postbox box-static" style="padding:12px 14px">
    <div class="field-row">
        <div class="field"><label for="course">درس</label>
            <select id="course" name="course">
                <option value="0">همهٔ دروس</option>
                <?php foreach ($courses as $c): ?>
                    <option value="<?= (int)$c['id'] ?>"<?= $courseF === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['title']) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="field"><label>از تاریخ</label><?= jdate_field('from', $fromF) ?></div>
        <div class="field"><label>تا تاریخ</label><?= jdate_field('to', $toF) ?></div>
        <div class="field checks"><label>&nbsp;</label>
            <label><input type="checkbox" name="range" value="1"<?= $useRange ? ' checked' : '' ?>> فقط این بازهٔ تاریخ</label></div>
        <div class="field"><label>&nbsp;</label>
            <span class="actions">
                <button type="submit" class="button">پالایش</button>
                <?php if ($baseQ): ?><a class="button" href="<?= e(url('admin/history.php')) ?>">حذف پالایه</a><?php endif; ?>
            </span></div>
    </div>
</form>

<form method="post" data-confirm-submit="رکورد(های) انتخاب‌شده حذف شود؟">
    <?= csrf_field() ?>
    <input type="hidden" name="qs" value="<?= e(http_build_query($curQ)) ?>">
    <div class="actions" style="margin-bottom:8px">
        <button type="submit" class="button" name="action" value="bulk-delete">حذف انتخاب‌شده‌ها</button>
        <span class="desc"><?= e(fnum($count)) ?> رکورد · <?= e(fnum($sumPages)) ?> صفحه · <?= e(fmt_minutes($sumMin)) ?></span>
    </div>
    <table class="list">
        <thead><tr>
            <th style="width:30px"><input type="checkbox" data-check-all=".row-cb" aria-label="انتخاب همه"></th>
            <th>تاریخ</th><th>درس</th><th class="num">صفحه</th><th class="num">زمان</th><th>یادداشت</th>
        </tr></thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="6" class="empty">رکوردی پیدا نشد.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><input type="checkbox" class="row-cb" name="ids[]" value="<?= (int)$r['id'] ?>"></td>
                <td><?= e(jfmt($r['log_date'], 'wd')) ?> <span class="desc"><?= e(fa(ymd_to_j($r['log_date'])[0])) ?></span>
                    <div class="row-actions">
                        <a href="<?= e(url('admin/log.php?id=' . (int)$r['id'])) ?>">ویرایش</a> |
                        <button type="submit" class="link-danger" name="delete_one" value="<?= (int)$r['id'] ?>">حذف</button>
                    </div></td>
                <td><span class="dot" style="background:<?= e($r['color']) ?>"></span> <?= e($r['title']) ?></td>
                <td class="num"><?= e(fnum($r['pages'])) ?></td>
                <td class="num"><?= e(fmt_minutes((int)$r['minutes'])) ?></td>
                <td><?= e($r['note']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</form>

<?php if ($pageCount > 1): ?>
    <p class="actions" style="margin-top:12px">
        <?php for ($i = 1; $i <= $pageCount; $i++):
            $q = $baseQ + ($i > 1 ? ['pg' => $i] : []); ?>
            <a class="button button-small<?= $i === $page ? ' button-primary' : '' ?>" href="?<?= e(http_build_query($q)) ?>"><?= e(fa($i)) ?></a>
        <?php endfor; ?>
    </p>
<?php endif; ?>
<?php admin_footer();
