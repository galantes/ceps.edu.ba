<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit(1);
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/admin-schema.php';
adminInstall(database());
echo "Admin tables ready; existing content preserved.\n";
