<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$_SERVER['REQUEST_URI']='/de';$_SERVER['HTTP_HOST']='127.0.0.1:8086';
require dirname(__DIR__).'/src/bootstrap.php';
if(!getenv('CEPS_DSN') && is_file(dirname(__DIR__,2).'/migration/runtime/db-password.txt')){
    putenv('CEPS_DSN=mysql:host=127.0.0.1;port=33079;dbname=ceps_preview;charset=utf8mb4');
    putenv('CEPS_DB_USER=root');putenv('CEPS_DB_PASSWORD='.trim(file_get_contents(dirname(__DIR__,2).'/migration/runtime/db-password.txt')));
}
function checkGerman(bool $condition,string $message): void {if(!$condition)throw new RuntimeException($message);}
$news=database()->query('SELECT ID,Naslov FROM Novosti WHERE De=1 ORDER BY ID')->fetchAll();$fixture=['pages'=>[],'news'=>[]];$checks=0;
foreach($news as $row){
    $expected='/de/nachrichten/'.$row['ID'].'-'.seoSlug($row['Naslov']);
    foreach(['/de/Novost.aspx?id='.$row['ID'],'https://de.ceps.edu.ba/Novost.aspx?ID=00'.$row['ID']] as $old){checkGerman(seoUrl($old)===$expected,'German news route: '.$old);$checks++;}
    $_SERVER['REQUEST_URI']=$expected;
    checkGerman(languageSwitchUrl('de')===$expected,'German switch lost article');$checks++;
    $fixture['news'][]=$expected;
}
$_SERVER['REQUEST_URI']='/de';
foreach(seoRoutes('de') as $old=>$route){
    if(!germanPageAvailable($old))continue;
    $expected=languagePrefix($route,'de');
    checkGerman(seoUrl(languagePrefix($old==='/'?'/Default.aspx':$old.'.aspx','de'))===$expected,'German static route: '.$old);$checks++;
    checkGerman(seoUrl($expected)===$expected,'German canonical not stable: '.$expected);$checks++;
    $fixture['pages'][]=$expected;
}
checkGerman(newsLanguageColumn()==='De','Wrong news column');
checkGerman(translate('Stranica 2 od 3')==='Seite 2 von 3','German pagination');
checkGerman(languageSwitchUrl(false)==='/' && languageSwitchUrl(true)==='/en','Switch to BS/EN');
$missing=(int)database()->query('SELECT ID FROM Novosti WHERE De=0 ORDER BY ID LIMIT 1')->fetchColumn();
checkGerman(!str_starts_with(seoUrl('/de/Novost.aspx?id='.$missing),'/de/'),'Non-German news under /de');
if(isset($argv[1]))file_put_contents($argv[1],json_encode($fixture,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo json_encode(['status'=>'passed','german_news'=>count($news),'german_routes'=>count($fixture['pages']),'checks'=>$checks+4],JSON_PRETTY_PRINT)."\n";
