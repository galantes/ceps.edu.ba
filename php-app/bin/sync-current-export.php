<?php
/** Sync parsed current export into the local preview, preserving site settings. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/admin-workflow.php';
$root=dirname(__DIR__,2);
$directory=$argv[1] ?? '';
if (!is_dir($directory)) throw new RuntimeException('Supply the directory containing current/local JSON snapshots.');
putenv('CEPS_DSN=mysql:host=127.0.0.1;port=33079;dbname=ceps_preview;charset=utf8mb4');
putenv('CEPS_DB_USER=root');
putenv('CEPS_DB_PASSWORD='.trim(file_get_contents($root.'/migration/runtime/db-password.txt')));
function syncValue(string $key, mixed $value): mixed {
    if ($value===null) return null;
    $value=(string)$value;
    if (in_array($key,['Datum','CreatedAt'],true)) {
        $value=str_replace('T',' ',$value);
        if (str_contains($value,'.')) $value=rtrim(rtrim($value,'0'),'.');
    }
    return $value;
}
$pdo=database();$report=[];
$settings=$pdo->query('SELECT SettingKey,Payload FROM AdminSettings ORDER BY SettingKey')->fetchAll();
$pdo->beginTransaction();
try {
    foreach (['Novosti','Sporazumi'] as $table) {
        $source=json_decode(file_get_contents($directory.'/current-'.$table.'.json'),true,512,JSON_THROW_ON_ERROR);
        $baseline=json_decode(file_get_contents($directory.'/local-'.$table.'.json'),true,512,JSON_THROW_ON_ERROR);
        $columns=array_column($pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(),'Field');
        $actual=[];
        foreach ($pdo->query('SELECT * FROM `'.$table.'` FOR UPDATE')->fetchAll() as $row) $actual[(int)$row['ID']]=$row;
        if (count($actual)!==count($baseline)) throw new RuntimeException('Local row count changed since snapshot: '.$table);
        foreach ($baseline as $row) {
            if (!isset($actual[$row['ID']])) throw new RuntimeException('Local ID changed since snapshot.');
            foreach ($row as $key=>$value) if (syncValue($key,$value)!==syncValue($key,$actual[$row['ID']][$key])) throw new RuntimeException('Local content changed since snapshot: '.$table.' '.$row['ID'].' '.$key);
        }
        $report[$table]=['inserted'=>[],'updated'=>[],'source_rows'=>count($source),'verified_fields'=>0];
        $seen=[];
        foreach ($source as $row) {
            $id=$row['ID'];
            if (!is_int($id) || $id<1 || isset($seen[$id])) throw new RuntimeException('Invalid or duplicate source ID.');
            $seen[$id]=true;
            if (array_diff(array_keys($row),$columns) || array_diff($columns,array_keys($row))) throw new RuntimeException('Source columns do not match local schema.');
            foreach (['Datum','CreatedAt'] as $key) if (isset($row[$key])) $row[$key]=str_replace('T',' ',$row[$key]);
            if (!isset($actual[$id])) {
                $names=array_keys($row);
                $pdo->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`',$names).'`) VALUES ('.implode(',',array_fill(0,count($names),'?')).')')->execute(array_values($row));
                $report[$table]['inserted'][]=$id;
            } else {
                $changes=[];
                foreach ($row as $key=>$value) if ($key!=='ID' && syncValue($key,$value)!==syncValue($key,$actual[$id][$key])) $changes[$key]=$value;
                if ($changes) {
                    if ($table==='Novosti') adminRevision($pdo,'news',(string)$id,$actual[$id]);
                    $set=implode(',',array_map(fn($key)=>'`'.$key.'`=?',array_keys($changes)));
                    $pdo->prepare('UPDATE `'.$table.'` SET '.$set.' WHERE ID=?')->execute([...array_values($changes),$id]);
                    $report[$table]['updated'][]=['ID'=>$id,'fields'=>array_keys($changes)];
                }
            }
        }
        $verified=[];
        foreach ($pdo->query('SELECT * FROM `'.$table.'`')->fetchAll() as $row) $verified[(int)$row['ID']]=$row;
        foreach ($source as $row) foreach ($row as $key=>$value) {
            if (syncValue($key,$value)!==syncValue($key,$verified[$row['ID']][$key])) throw new RuntimeException('Verification failed: '.$table.' '.$row['ID'].' '.$key);
            $report[$table]['verified_fields']++;
        }
        $report[$table]['local_rows_after']=count($verified);
    }
    if ($settings!==$pdo->query('SELECT SettingKey,Payload FROM AdminSettings ORDER BY SettingKey')->fetchAll()) throw new RuntimeException('Site settings changed unexpectedly.');
    $pdo->commit();
} catch (Throwable $error) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $error; }
file_put_contents($directory.'/applied.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
