<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/src/bootstrap.php';
$output = dirname(__DIR__, 2) . '/migration/output';
$serverDsn = getenv('CEPS_SERVER_DSN');
$name = getenv('CEPS_IMPORT_DATABASE') ?: 'ceps_preview';
if (!$serverDsn || !preg_match('/^[a-zA-Z0-9_]+$/D', $name)) throw new RuntimeException('Set CEPS_SERVER_DSN and a valid CEPS_IMPORT_DATABASE.');
$db = new PDO($serverDsn, getenv('CEPS_DB_USER'), getenv('CEPS_DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (!in_array('--verify-only', $argv, true)) {
    // Deliberately fails if database exists. Never overwrite an existing database.
    $db->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $db->exec("USE `$name`");
    $sql = file_get_contents($output . '/mysql-staging.sql');
    foreach (preg_split('/;\s*(?:\r?\n|$)/', $sql) as $statement) {
        if (trim($statement) !== '') $db->exec($statement);
    }
} else {
    $db->exec("USE `$name`");
}
$report = ['engine' => $db->getAttribute(PDO::ATTR_SERVER_VERSION), 'database' => $name, 'tables' => []];
foreach (['Novosti', 'Sporazumi'] as $table) {
    $expected = json_decode(file_get_contents($output . '/' . $table . '.json'), true, 512, JSON_THROW_ON_ERROR);
    usort($expected, fn($a, $b) => $a['ID'] <=> $b['ID']);
    $actual = $db->query("SELECT * FROM `$table` ORDER BY ID")->fetchAll(PDO::FETCH_ASSOC);
    if (count($expected) !== count($actual)) throw new RuntimeException("Row count mismatch: $table");
    $fields = 0;
    foreach ($expected as $index => $row) {
        foreach ($row as $key => $value) {
            $received = $actual[$index][$key];
            if (in_array($key, ['Datum', 'CreatedAt'], true) && $value !== null) $value = str_replace('T', ' ', $value);
            if ($value !== $received) throw new RuntimeException("Field mismatch: $table ID={$row['ID']} $key");
            $fields++;
        }
    }
    $report['tables'][$table] = ['rows' => count($expected), 'fields_verified' => $fields, 'result' => 'identical'];
}
$report['source_sha256'] = json_decode(file_get_contents($output . '/summary.json'), true)['source_sha256'];
file_put_contents($output . '/mysql-verification.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
