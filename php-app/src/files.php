<?php
declare(strict_types=1);

function serveLegacyFile(string $path): never {
    $root = dirname(__DIR__, 2);
    $handle = fopen($root . '/migration/output/files.csv', 'r');
    if (!$handle) throw new RuntimeException('Missing file manifest');
    fgetcsv($handle, escape: '');
    $found = null;
    while (($record = fgetcsv($handle, escape: '')) !== false) {
        if (strcasecmp('/' . $record[0], $path) === 0) {
            $found = $record[0];
            if ('/' . $found === $path) break;
        }
    }
    fclose($handle);
    $types = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','ico'=>'image/x-icon','pdf'=>'application/pdf','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document','xls'=>'application/vnd.ms-excel','xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','ppt'=>'application/vnd.ms-powerpoint','pptx'=>'application/vnd.openxmlformats-officedocument.presentationml.presentation','zip'=>'application/zip','rar'=>'application/vnd.rar','mp4'=>'video/mp4','mp3'=>'audio/mpeg'];
    $extension = strtolower(pathinfo($found ?? '', PATHINFO_EXTENSION));
    $file = $found ? realpath($root . '/' . $found) : false;
    $base = realpath($root . '/Files');
    if (!$found || !$file || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !isset($types[$extension])) {
        layout('Datoteka nije pronađena', '', '', '<section class="empty"><h1>Datoteka nije pronađena.</h1></section>', 404);
    }
    $canonical = '/' . implode('/', array_map('rawurlencode', explode('/', $found)));
    if ('/' . $found !== $path) {
        header('Location: ' . $canonical, true, 301);
        exit;
    }
    header('Content-Type: ' . $types[$extension]);
    header('X-Content-Type-Options: nosniff');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: public, max-age=3600');
    if ($_SERVER['REQUEST_METHOD'] !== 'HEAD') readfile($file);
    exit;
}
