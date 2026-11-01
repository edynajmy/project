<?php
declare(strict_types=1);

// Resolve the base URL from the actual folder name under htdocs.
// This supports both `berjaya-hills` and `berjaya_hills`.
define('BASE_URL', '/' . basename(dirname(__DIR__)) . '/');
define('APP_NAME', 'Berjaya Hills Resort');
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'berjaya_hills');
define('DB_USER', 'root');
define('DB_PASS', '');
define('TIMEZONE', 'Asia/Kuala_Lumpur');

date_default_timezone_set(TIMEZONE);
