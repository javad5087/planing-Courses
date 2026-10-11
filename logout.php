<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
start_session();

// خروج با توکن CSRF (جلوگیری از خروج اجباری از طریق لینک‌های خارجی)
if (current_user() && hash_equals(csrf_token(), (string)($_GET['t'] ?? ''))) {
    logout_user();
}
redirect(url('login.php'));
