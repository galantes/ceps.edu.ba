<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$path = dirname(__DIR__).'/var/admin.json';
if (is_file($path)) { fwrite(STDERR, "Admin account already exists.\n"); exit(1); }
fwrite(STDERR, "Username: ");
$user = ltrim(trim(fgets(STDIN) ?: ''), "\xEF\xBB\xBF");
fwrite(STDERR, "Password (at least 14 characters; terminal input may be visible): ");
$password = rtrim(fgets(STDIN) ?: '', "\r\n");
if (!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/D', $user) || strlen($password)<14) { fwrite(STDERR, "Invalid username or password length.\n"); exit(1); }
if (!is_dir(dirname($path))) mkdir(dirname($path),0700,true);
$handle = fopen($path,'x');
if (!$handle) exit(1);
fwrite($handle,json_encode(['username'=>$user,'password_hash'=>password_hash($password,PASSWORD_DEFAULT)],JSON_PRETTY_PRINT));
fclose($handle);chmod($path,0600);
fwrite(STDERR,"Account created. Open /admin.\n");
