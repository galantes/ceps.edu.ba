<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/admin.php';
require dirname(__DIR__).'/src/admin-schema.php';
$root=dirname(__DIR__,2);
$password=trim(file_get_contents($root.'/migration/runtime/db-password.txt'));
$server=new PDO('mysql:host=127.0.0.1;port=33079;charset=utf8mb4','root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='ceps_admin_test_'.bin2hex(random_bytes(5));
$server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
try {
    $server->exec("CREATE TABLE `$name`.Novosti LIKE ceps_preview.Novosti");
    $server->exec("CREATE TABLE `$name`.Sporazumi LIKE ceps_preview.Sporazumi");
    putenv('CEPS_DSN=mysql:host=127.0.0.1;port=33079;dbname='.$name.';charset=utf8mb4');putenv('CEPS_DB_USER=root');putenv('CEPS_DB_PASSWORD='.$password);
    $pdo=database();adminInstall($pdo);
    $data=adminValidate('news',['Naslov'=>'MySQL provjera','Tekst'=>'<p>Čćšžđ</p>','Datum'=>'2026-09-29T12:00','Hr'=>'1']);
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO Novosti ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')')->execute(array_values($data));
    $id=(string)$pdo->lastInsertId();$row=$pdo->query('SELECT * FROM Novosti WHERE ID='.$id.' FOR UPDATE')->fetch();
    adminRevision($pdo,'news',$id,$row);$pdo->commit();
    $draft=adminSaveDraft($pdo,'news',$id,$data,adminFingerprint($row),null);
    if(!$draft || $pdo->query('SELECT COUNT(*) FROM AdminRevisions')->fetchColumn()!=1)throw new RuntimeException('Workflow failed');
    $agreement=adminValidate('agreements',['Naziv'=>'MySQL sporazum','Datum'=>'2026-09-29T12:00','Putanja'=>'/Files/test.pdf','Kategorija'=>'privreda','Aktivan'=>'1']);
    $agreement['CreatedAt']=date('Y-m-d H:i:s');
    $pdo->prepare('INSERT INTO Sporazumi ('.implode(',',array_keys($agreement)).') VALUES ('.implode(',',array_fill(0,count($agreement),'?')).')')->execute(array_values($agreement));
    echo "PASS: MySQL schema, news insert, row lock, revisions, drafts, agreement insert and UTF-8.\n";
} finally {$server->exec("DROP DATABASE `$name`");}
