<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$target = $argv[1] ?? '';
if (!$target || file_exists($target)) throw new RuntimeException('Supply a new SQL output path.');
$password = trim(file_get_contents($root.'/migration/runtime/db-password.txt'));
$pdo = new PDO('mysql:host=127.0.0.1;port=33079;dbname=ceps_preview;charset=utf8mb4', 'root', $password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$file = fopen($target, 'xb');
if (!$file) throw new RuntimeException('Cannot create export.');
fwrite($file, "-- CEPS test database. Import into an EMPTY database.\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
$pdo->beginTransaction();
$counts = [];
$tableNames = ['novosti'=>'Novosti', 'sporazumi'=>'Sporazumi', 'admindrafts'=>'AdminDrafts', 'adminrevisions'=>'AdminRevisions', 'adminsettings'=>'AdminSettings', 'adminmedia'=>'AdminMedia'];
try {
    foreach ($pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $record) {
        $table = $record[0];
        $quoted = '`'.str_replace('`','``',$table).'`';
        $destination = $tableNames[strtolower($table)] ?? throw new RuntimeException('Unexpected table: '.$table);
        $outputTable = '`'.$destination.'`';
        $create = $pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
        $create = preg_replace('/^CREATE TABLE `[^`]+`/', 'CREATE TABLE '.$outputTable, $create, 1);
        fwrite($file, $create.";\n");
        $count = 0;
        foreach ($pdo->query('SELECT * FROM '.$quoted) as $row) {
            $values = []; $columns = [];
            foreach ($row as $column=>$value) {
                if (!is_string($column)) continue;
                $columns[] = '`'.str_replace('`','``',$column).'`';
                $values[] = $value === null ? 'NULL' : $pdo->quote((string)$value);
            }
            fwrite($file, 'INSERT INTO '.$outputTable.' ('.implode(',',$columns).') VALUES ('.implode(',',$values).");\n");
            $count++;
        }
        $counts[$table] = $count;
    }
    $pdo->commit();
    fwrite($file, "SET FOREIGN_KEY_CHECKS=1;\n");
} finally { fclose($file); if ($pdo->inTransaction()) $pdo->rollBack(); }
echo json_encode($counts, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
