<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$root=dirname(__DIR__,2);
$password=trim(file_get_contents($root.'/migration/runtime/db-password.txt'));
$pdo=new PDO('mysql:host=127.0.0.1;port=33079;charset=utf8mb4','root',$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='ceps_export_check_'.bin2hex(random_bytes(6));
$sql=file_get_contents($argv[1]);
$tables=['Novosti','Sporazumi','AdminDrafts','AdminRevisions','AdminSettings','AdminMedia'];
foreach($tables as $table) if(!str_contains($sql,'CREATE TABLE `'.$table.'`'))throw new RuntimeException('Incorrect table casing: '.$table);
if(preg_match('/^(?:DROP|USE|CREATE DATABASE)\s/im',$sql))throw new RuntimeException('Unsafe SQL statement');
$pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
try {
    $pdo->exec('USE `'.$name.'`');
    $pdo->exec($sql);
    foreach($tables as $table){
        $hashes=static function(string $db)use($pdo,$table):array{
            $result=[];
            foreach($pdo->query('SELECT * FROM `'.$db.'`.`'.$table.'`',PDO::FETCH_ASSOC) as $row){$result[]=hash('sha256',serialize(array_map(static fn($v)=>$v===null?null:(string)$v,$row)));}
            sort($result);return $result;
        };
        if($hashes('ceps_preview')!==$hashes($name))throw new RuntimeException('Data mismatch: '.$table);
    }
    echo "SQL import verified: all six tables and every row match the local database.\n";
} finally { $pdo->exec('DROP DATABASE `'.$name.'`'); }
