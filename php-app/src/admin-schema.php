<?php
declare(strict_types=1);
function adminInstall(PDO $pdo): void {
    $mysql=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql';
    if(!$mysql)$pdo->exec('PRAGMA query_only = OFF');
    $id=$mysql?'INTEGER PRIMARY KEY AUTO_INCREMENT':'INTEGER PRIMARY KEY AUTOINCREMENT';
    $text=$mysql?'LONGTEXT':'TEXT';
    $pdo->exec("CREATE TABLE IF NOT EXISTS AdminDrafts (ID $id, Module VARCHAR(20) NOT NULL, RecordID INTEGER NULL, Payload $text NOT NULL, BaseVersion VARCHAR(64) NOT NULL, UpdatedAt VARCHAR(30) NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS AdminRevisions (ID $id, Module VARCHAR(20) NOT NULL, RecordID INTEGER NOT NULL, Payload $text NOT NULL, CreatedAt VARCHAR(30) NOT NULL)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS AdminSettings (SettingKey VARCHAR(40) PRIMARY KEY, Payload $text NOT NULL)");
    require_once __DIR__.'/home-settings.php';
    if(!$pdo->query("SELECT COUNT(*) FROM AdminSettings WHERE SettingKey='home'")->fetchColumn())$pdo->prepare('INSERT INTO AdminSettings (SettingKey,Payload) VALUES (?,?)')->execute(['home',json_encode(homeDefaults(),JSON_UNESCAPED_UNICODE)]);
    $pdo->exec("CREATE TABLE IF NOT EXISTS AdminMedia (ID $id, Filename VARCHAR(80) NOT NULL UNIQUE, OriginalName VARCHAR(255) NOT NULL, Mime VARCHAR(60) NOT NULL, Bytes INTEGER NOT NULL, CreatedAt VARCHAR(30) NOT NULL)");
}
