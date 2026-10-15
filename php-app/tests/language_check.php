<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/src/bootstrap.php';
if(!getenv('CEPS_DSN') && is_file(dirname(__DIR__,2).'/migration/runtime/db-password.txt')){
 putenv('CEPS_DSN=mysql:host=127.0.0.1;port=33079;dbname=ceps_preview;charset=utf8mb4');putenv('CEPS_DB_USER=root');putenv('CEPS_DB_PASSWORD='.trim(file_get_contents(dirname(__DIR__,2).'/migration/runtime/db-password.txt')));
}
$news=database()->query('SELECT ID,Hr,En,Naslov FROM Novosti ORDER BY ID')->fetchAll();$checks=0;$fixture=[];
foreach($news as $row){
 $id=(string)$row['ID'];$bs='/novosti/'.$id.'-'.seoSlug($row['Naslov']);$en=$row['En']?'/en/news/'.$id.'-'.seoSlug($row['Naslov']):$bs;
 foreach(['/Novost.aspx?id='.$id,'/Novost?id='.$id,'/novost.aspx?ID=00'.$id] as $old){if(seoUrl($old,false)!==$bs)throw new RuntimeException('Bosnian redirect mismatch: '.$old);$checks++;}
 foreach(['/en/Novost.aspx?id='.$id,'https://en.ceps.edu.ba/Novost.aspx?id='.$id] as $old){if(seoUrl($old,true)!==$en)throw new RuntimeException('English redirect mismatch: '.$old);$checks++;}
 if(seoUrl('/Novost.aspx?id='.$id.'&utm_source=archive',false)!==$bs.'?utm_source=archive')throw new RuntimeException('Query lost: '.$id);
 $fixture[]=['id'=>(int)$id,'bs'=>$bs,'en'=>$en,'english'=>(bool)$row['En'],'bosnian'=>(bool)$row['Hr'],'title'=>titleText($row['Naslov'])];
}
foreach([false,true] as $english)foreach(seoRoutes($english) as $old=>$new){
 foreach([$old,$old==='/'?'/Default.aspx':$old.'.aspx'] as $source){$source=languagePrefix($source,$english);$expected=languagePrefix($new,$english);if(seoUrl($source,$english)!==$expected)throw new RuntimeException('Static route mismatch: '.$source);$checks++;}
}
if(seoUrl('https://en.ceps.edu.ba/knowledge-hub-for-climate-and-sustainability.aspx',true)!=='/en/knowledge-hub-for-climate-and-sustainability')throw new RuntimeException('KHCS alias missing');
foreach(['/Files/document.pdf','/Files/document.pdf?v=1#page=2','https://example.org/Novost.aspx?id=1','#section','mailto:info@ceps.edu.ba'] as $url)if(seoUrl($url,true)!==$url)throw new RuntimeException('Asset or external link changed: '.$url);
if(isset($argv[1]))file_put_contents($argv[1],json_encode(['news'=>$fixture,'bs_routes'=>seoRoutes(false),'en_routes'=>seoRoutes(true)],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
echo json_encode(['news'=>count($news),'english_news'=>count(array_filter($news,fn($r)=>$r['En'])),'redirect_checks'=>$checks,'status'=>'passed'],JSON_PRETTY_PRINT)."\n";
