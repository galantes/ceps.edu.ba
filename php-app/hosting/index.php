<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$app = dirname(__DIR__, 2) . '/ceps-app/php-app';
$config = require $app . '/config.local.php';
foreach ($config as $name => $value) putenv($name . '=' . $value);
require $app . '/public/index.php';
