<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
start_session();
redirect(current_user() ? url('admin/dashboard.php') : url('login.php'));
