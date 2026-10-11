<?php
// تنظیمات پایه — در صورت نیاز مسیرها را تغییر دهید.
declare(strict_types=1);

define('APP_ROOT', __DIR__);
// پیشنهاد امنیتی: متغیر محیطی DARSYAR_DATA_DIR را روی پوشه‌ای خارج از public_html بگذارید.
$__dataDir = getenv('DARSYAR_DATA_DIR');
define('DATA_DIR', rtrim(($__dataDir !== false && $__dataDir !== '') ? $__dataDir : APP_ROOT . '/data', '/\\'));
define('APP_VERSION', '1.0.0');
unset($__dataDir);

date_default_timezone_set('Asia/Tehran');
mb_internal_encoding('UTF-8');

// آدرس پایه برنامه (بر اساس مسیر همین پوشه)، بدون اسلش پایانی
$__script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
$__dir = rtrim(dirname($__script), '/');
if (substr($__dir, -6) === '/admin') {
    $__dir = substr($__dir, 0, -6);
}
define('BASE_URL', $__dir);
unset($__script, $__dir);

if (!is_dir(DATA_DIR)) {
    @mkdir(DATA_DIR, 0750, true);
}

// نام فایل دیتابیس تصادفی است و در یک فایل .php نگه‌داری می‌شود (باز کردنش از مرورگر چیزی نشان نمی‌دهد).
// این کار حتی روی سرورهایی که .htaccess را نمی‌خوانند (مثل nginx) حدس زدن آدرس دیتابیس را غیرممکن می‌کند.
$__nameFile = DATA_DIR . '/dbname.php';
if (!is_file($__nameFile) && is_writable(DATA_DIR)) {
    @file_put_contents($__nameFile, "<?php return 'darsyar-" . bin2hex(random_bytes(16)) . ".sqlite';\n", LOCK_EX);
}
define('DB_FILE', DATA_DIR . '/' . (is_file($__nameFile) ? (string)(require $__nameFile) : 'darsyar.sqlite'));
unset($__nameFile);

require_once APP_ROOT . '/inc/helpers.php';
require_once APP_ROOT . '/inc/jalali.php';
require_once APP_ROOT . '/inc/db.php';
require_once APP_ROOT . '/inc/auth.php';
require_once APP_ROOT . '/inc/plan.php';
require_once APP_ROOT . '/inc/layout.php';
