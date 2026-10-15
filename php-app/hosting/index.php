<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
$app = __DIR__ . '/app';
ini_set('error_log', $app . '/var/php-error.log');
ini_set('session.save_path', $app . '/var/sessions');
$config = require $app . '/config.local.php';
foreach ($config as $name => $value) putenv($name . '=' . $value);
putenv('CEPS_FILES_ROOT=' . __DIR__);
putenv('CEPS_FILES_MANIFEST=' . $app . '/content/files.csv');
require $app . '/public/index.php';
