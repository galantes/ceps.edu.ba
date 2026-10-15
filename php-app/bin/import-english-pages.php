<?php
/** Import saved public English pages. Usage: php import-english-pages.php SOURCE_DIRECTORY */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/bootstrap.php';
$directory=$argv[1] ?? '';if(!is_dir($directory))throw new RuntimeException('Supply saved English source directory.');
$base=dirname(__DIR__).'/content/';
$pages=array_merge(json_decode(file_get_contents($base.'pages.json'),true),json_decode(file_get_contents($base.'projects.json'),true));
$titles=['Misija'=>'Mission, vision and goals','Akreditacija'=>'Accreditation','Akademska_organizacija'=>'Academic Organisation','Akti'=>'Acts and Bodies of the institution','Bolonjski_proces'=>'Bologna process','Kvaliteta'=>'Quality Assurance','Maticni_podaci'=>'Master data','Knjiznica'=>'Library and publishing','Cjelozivotno_ucenje'=>'Lifelong learning','Studiji'=>'Study programmes','StudijIT'=>'Information technology','StudijPE'=>'Business Economy','StudijPromet'=>'Traffic Engineering','StudijEnergetika'=>'Energetics','StudijSigurnost'=>'Security studies','StudijZdravstvo'=>'Health studies','StudijZastita'=>'Safety','StudijEng'=>'Studies in English','pravne-nauke'=>'The law study','Upisi'=>'Admission','Placanje-u-ratama'=>'Tuition in instalments','Cisco'=>'Cisco Networking Academy','Zgrada'=>'CEPS campus','Projekti'=>'Projects','DIT'=>'The social and technical research','centar-za-klimu-i-odrzivost'=>'Knowledge Hub for Climate and Sustainability (KHCS)','Alumni'=>'Alumni association','Maturanti'=>'Workshop for school leavers','prijava-andragoska-obuka'=>'Adult education training application','Galerija'=>'Media','Medunarodna_suradnja'=>'International Relations','istrazivacki-centar'=>'Research center','Kontakt'=>'Contact','Alerrt'=>'ALERRT','Crase'=>'CRASE','360-virtual-tour'=>'360° virtual tour'];
function loadEnglishSource(string $file):DOMDocument{
 $doc=new DOMDocument('1.0','UTF-8');libxml_use_internal_errors(true);$doc->loadHTML('<?xml encoding="UTF-8">'.file_get_contents($file),LIBXML_NONET);libxml_clear_errors();return $doc;
}
function innerEnglish(DOMNode $node):string{$s='';foreach($node->childNodes as $child)$s.=$node->ownerDocument->saveHTML($child);return $s;}
function cleanEnglish(DOMNode $node,string $slug):string{
 $doc=$node->ownerDocument;$xp=new DOMXPath($doc);
 foreach(iterator_to_array($xp->query('.//*[self::script or self::style or self::input or self::button or self::select or contains(@id,"DataPager")]', $node)) as $n)$n->parentNode?->removeChild($n);
 foreach(iterator_to_array($xp->query('.//*[contains(@id,"UpdatePanel")]', $node)) as $n){
  $marker=$slug==='Medunarodna_suradnja'?'{{Aktivnosti}}':($slug==='istrazivacki-centar'?'{{projekt_15}}':'');
  $n->parentNode?->replaceChild($doc->createTextNode($marker),$n);
 }
 foreach(iterator_to_array($xp->query('.//span[contains(@class,"title_")]', $node)) as $n){$h=$doc->createElement('h2');$h->appendChild($doc->createTextNode(trim($n->textContent)));$n->parentNode?->replaceChild($h,$n);}
 // Mission/vision tables are layout, not tabular data.
 if($slug==='Misija')foreach(iterator_to_array($xp->query('.//table',$node)) as $table){$cells=$table->getElementsByTagName('td');if($cells->length!==2)continue;$section=$doc->createElement('div');$h=$doc->createElement('h2',trim($cells->item(0)->textContent));$section->appendChild($h);$body=$cells->item(1);while($body->firstChild)$section->appendChild($body->firstChild);$table->parentNode?->replaceChild($section,$table);}
 foreach(['a'=>'href','img'=>'src'] as $tag=>$attribute)foreach($node->getElementsByTagName($tag) as $n){
  $url=trim($n->getAttribute($attribute));if($url==='' || $url[0]==='#')continue;
  $url=str_replace('www.ceps.edu.baFiles/','www.ceps.edu.ba/Files/',$url);
  if(str_starts_with($url,'~/'))$url=substr($url,1);
  if(!preg_match('~^(?:[a-z]+:|//)~i',$url)){
   $path=str_starts_with($url,'/')?$url:('/'.(str_starts_with($slug,'Projekt/')?'Projekt/':'').$url);
   $segments=[];foreach(explode('/',$path) as $part){if($part==='..')array_pop($segments);elseif($part!=='.' && $part!=='')$segments[]=$part;}
   $url='https://ceps.edu.ba/'.implode('/',$segments);
  }
  $url=preg_replace('~^http://((?:www\.|en\.)?ceps\.edu\.ba)/~i','https://$1/',$url);
  if($tag==='img' && str_contains($url,'Thumb.ashx') && $n->parentNode instanceof DOMElement && $n->parentNode->tagName==='a')$url=$n->parentNode->getAttribute('href');
  $n->setAttribute($attribute,$url);
 }
 $html=articleHtml(innerEnglish($node));
 // Content sanitation intentionally discards old layout colours and sizing.
 $html=preg_replace('~\sstyle="[^"]*"~i','',$html);
 return $html;
}
$result=[];$report=[];
foreach($pages as $key=>$page){
 $slug=$page['slug'];$file=$directory.'/'.str_replace('/','__',$slug).'.html';$entry=['title'=>$titles[$slug] ?? $page['title'],'html'=>$page['html']];
 if(is_file($file)){
  $doc=loadEnglishSource($file);$xp=new DOMXPath($doc);$main=$xp->query('//*[@id="Content"]')->item(0);if(!$main)throw new RuntimeException('Missing content: '.$slug);
  if(str_starts_with($slug,'Projekt/')){
   $left=$xp->query('.//*[@id="LeftContent"]',$main)->item(0);
   // Each language list starts at its label. Never freeze old news into static content.
   if($left)foreach(iterator_to_array($left->getElementsByTagName('b')) as $b)if(preg_match('~Objave vezane|Posts related~u',$b->textContent)){
    $label=$b->parentNode;while($label->nextSibling)$label->parentNode->removeChild($label->nextSibling);$label->parentNode->removeChild($label);break;
   }
   $right=$xp->query('.//*[@id="RightContent"]',$main)->item(0);$entry['resources']=$right?cleanEnglish($right,$slug):'';if($right)$right->parentNode->removeChild($right);
   $heading=$main->getElementsByTagName('h2')->item(0);if($heading){$entry['title']=trim(preg_replace('/\s+/u',' ',$heading->textContent));$heading->parentNode->removeChild($heading);}
  }
  $entry['html']=cleanEnglish($main,$slug);$report[$key]=['source'=>'https://en.ceps.edu.ba/'.($slug==='centar-za-klimu-i-odrzivost'?'knowledge-hub-for-climate-and-sustainability':$slug).'.aspx'];
 }else{$report[$key]=['source'=>'translated from current Bosnian page'];if(isset($page['resources']))$entry['resources']=$page['resources'];}
 // Partner lists always use the same current database and official institution names.
 if($slug==='Medunarodna_suradnja'){
  // Keep current nine-section structure for shared tabs; translate its static copy separately.
  $entry['html']=$page['html'];$report[$key]['shared_partner_source']='current Bosnian page and Sporazumi database';
 }
 if($slug==='Kontakt' || $slug==='Galerija' || in_array($slug,['Alerrt','Crase'],true))$entry['html']=$page['html'];
 $result[$key]=$entry;
}
file_put_contents($base.'en/pages.json',json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
file_put_contents($base.'en/sources.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
// Reuse the English welcome text and programme descriptions, retaining current media/documents.
$home=json_decode(file_get_contents($base.'home.json'),true);$doc=loadEnglishSource($directory.'/Default.html');$xp=new DOMXPath($doc);
// Keep the current homepage's three welcome paragraphs; translate them below.
foreach($home['programmes'] as &$programme){$key=strtolower(pathinfo($programme['url'],PATHINFO_FILENAME));$programme['title']=$titles[pathinfo($programme['url'],PATHINFO_FILENAME)] ?? $programme['title'];
 if(isset($result[$key])){$d=new DOMDocument();$d->loadHTML('<?xml encoding="UTF-8">'.$result[$key]['html'],LIBXML_NONET);$paragraph=$d->getElementsByTagName('p')->item(0);if($paragraph)$programme['paragraphs']=[trim(preg_replace('/\s+/u',' ',$paragraph->textContent))];}
}
unset($programme);file_put_contents($base.'en/home.json',json_encode($home,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$doc=loadEnglishSource($directory.'/Onama.html');$xp=new DOMXPath($doc);$node=$xp->query('//*[@id="Content"]//*[@id="LeftContent"]')->item(0);$about=json_decode(file_get_contents($base.'about.json'),true);$about['paragraphs']=[];foreach($node->getElementsByTagName('p') as $p)$about['paragraphs'][]=trim(preg_replace('/\s+/u',' ',$p->textContent));file_put_contents($base.'en/about.json',json_encode($about,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo count($result)." English page entries imported\n";

$englishSourceDirectory=$directory;require __DIR__.'/translate-english-content.php';
