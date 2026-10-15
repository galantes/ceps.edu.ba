<?php
/** Import original public German copy. Usage: php import-german-pages.php SOURCE_DIRECTORY */
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/src/bootstrap.php';
$directory=$argv[1] ?? '';
if(!is_dir($directory))throw new RuntimeException('Supply saved German source directory.');
$base=dirname(__DIR__).'/content/';
function germanSource(string $file): DOMDocument {
    $doc=new DOMDocument('1.0','UTF-8');$previous=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">'.file_get_contents($file),LIBXML_NONET);
    libxml_clear_errors();libxml_use_internal_errors($previous);return $doc;
}
function germanText(DOMNode $node): string {return trim(preg_replace('/\s+/u',' ',$node->textContent));}
function germanContent(DOMElement $node): string {
    $doc=$node->ownerDocument;$xp=new DOMXPath($doc);
    // News stays in the database; discard old interactive controls and layout sidebars.
    if($xp->query('.//span[contains(@class,"title_ms")]',$node)->length) {
        foreach(iterator_to_array($xp->query('.//*[contains(@id,"UpdatePanel")]',$node)) as $panel)$panel->parentNode?->replaceChild($doc->createTextNode('{{Aktivnosti}}'),$panel);
    }
    foreach(iterator_to_array($xp->query('.//*[self::script or self::style or self::input or self::button or self::select or contains(@id,"UpdatePanel") or contains(@id,"DataPager")]',$node)) as $n)$n->parentNode?->removeChild($n);
    foreach(iterator_to_array($xp->query('.//div[contains(@class,"Smjer")][span[normalize-space(.)="Press" or normalize-space(.)="Presse"]]',$node)) as $n)$n->parentNode?->removeChild($n);
    foreach(iterator_to_array($xp->query('.//span[contains(@class,"title_")]',$node)) as $n){$heading=$doc->createElement('h2');$heading->appendChild($doc->createTextNode(germanText($n)));$n->parentNode?->replaceChild($heading,$n);}
    foreach(['a'=>'href','img'=>'src'] as $tag=>$attribute)foreach($node->getElementsByTagName($tag) as $n){
        $url=trim($n->getAttribute($attribute));if($url==='' || $url[0]==='#')continue;
        $url=str_replace(['~/','www.ceps.edu.baFiles/'],['/','www.ceps.edu.ba/Files/'],$url);
        // The source omitted the leading underscore from this existing book's filename.
        $url=str_replace('/Izdanja/Upravljanje%20kvalitetom%20-%20karika%20koja%20nedostaje.pdf','/Izdanja/_Upravljanje%20kvalitetom%20-%20karika%20koja%20nedostaje.pdf',$url);
        if(!preg_match('~^(?:[a-z]+:|//)~i',$url))$url='/'.ltrim($url,'./');
        $url=preg_replace('~^http://((?:www\.|de\.)?ceps\.edu\.ba)/~i','https://$1/',$url);
        if(preg_match('~^/Files/~i',$url))$url='https://ceps.edu.ba'.$url;
        $n->setAttribute($attribute,$url);
    }
    $html='';foreach($node->childNodes as $child)$html.=$doc->saveHTML($child);
    return preg_replace('~\sstyle="[^"]*"~i','',articleHtml($html));
}
$titles=['Akreditacija'=>'Akkreditierung','Akademska_organizacija'=>'Akademische Organisation','DIT'=>'Die soziale und technische Forschung','Bolonjski_proces'=>'Der Bologna-Prozess','Kvaliteta'=>'Qualitätssicherung','Misija'=>'Vision, Mission und Ziele','Knjiznica'=>'Bibliothek','Akti'=>'Akten und Leitung der FH','Maticni_podaci'=>'Stammdaten','Studiji'=>'Studiengänge','StudijEng'=>'Studium in Englisch','StudijPromet'=>'Verkehrswesen','StudijEnergetika'=>'Energiewirtschaft','StudijSigurnost'=>'Sicherheitsstudien','StudijPE'=>'Betriebswirtschaft','StudijIT'=>'Informationstechnologie','StudijZastita'=>'Arbeitsschutz und Brandschutz','Medunarodna_suradnja'=>'Internationale Beziehungen','Galerija'=>'Galerie','Kontakt'=>'Kontakt','Upisi'=>'Aufnahme'];
$pages=json_decode(file_get_contents($base.'pages.json'),true);$result=[];$sources=[];
foreach($titles as $slug=>$title){
    $doc=germanSource($directory.'/'.$slug.'.html');$xp=new DOMXPath($doc);
    $node=$xp->query('//*[@id="Content"]')->item(0);
    if(!$node)throw new RuntimeException('Missing German content: '.$slug);
    // Remove the repeated page heading, retaining all original body copy and document links.
    $heading=$xp->query('./h2[1]',$node)->item(0);if($heading)$heading->parentNode->removeChild($heading);
    $result[strtolower($slug)]=['title'=>$title,'html'=>germanContent($node)];
    $sources[strtolower($slug)]=['source'=>'https://de.ceps.edu.ba/'.$slug.'.aspx'];
}
$home=json_decode(file_get_contents($base.'home.json'),true);
$doc=germanSource($directory.'/Default.html');$xp=new DOMXPath($doc);$home['welcome']=[];
foreach($xp->query('//*[@id="LeftContent"]/p') as $p)$home['welcome'][]=germanText($p);
$home['features']=[];
foreach($xp->query('//*[@id="Div2"] | //*[@id="Div4"]') as $section){
    $feature=null;
    foreach($section->childNodes as $child){
        if($child instanceof DOMElement && $child->tagName==='span' && str_contains($child->getAttribute('class'),'title_')){
            if($feature)$home['features'][]=$feature;
            $feature=['title'=>germanText($child),'paragraphs'=>[]];
        }elseif($feature && $child instanceof DOMElement && $child->tagName==='p')$feature['paragraphs'][]=germanText($child);
    }
    if($feature)$home['features'][]=$feature;
}
foreach($home['programmes'] as &$programme){
    $slug=pathinfo($programme['url'],PATHINFO_FILENAME);$key=strtolower($slug);
    if(isset($result[$key])){
        $programme['title']=$result[$key]['title'];
        $d=new DOMDocument();$previous=libxml_use_internal_errors(true);$d->loadHTML('<?xml encoding="UTF-8">'.$result[$key]['html'],LIBXML_NONET);libxml_clear_errors();libxml_use_internal_errors($previous);
        $paragraph=$d->getElementsByTagName('p')->item(0);$programme['paragraphs']=$paragraph?[germanText($paragraph)]:[];
    } elseif($slug==='Zgrada'){$programme['title']='CEPS-Campus';$programme['paragraphs']=['Besuchen Sie die Fachhochschule CEPS in Kiseljak und lernen Sie die Räumlichkeiten kennen.'];}
    elseif($slug==='Cisco'){$programme['title']='Cisco Networking Academy';$programme['paragraphs']=['Die Cisco Networking Academy vermittelt Kenntnisse für die Planung, den Aufbau und die Verwaltung von Computernetzwerken.'];}
}
unset($programme);
// Document titles are translated by the interface dictionary; source PDFs remain unchanged.
$about=json_decode(file_get_contents($base.'about.json'),true);$about['paragraphs']=[];
$doc=germanSource($directory.'/Onama.html');$xp=new DOMXPath($doc);
foreach($xp->query('//*[@id="LeftContent"]//p') as $p)if(germanText($p)!=='')$about['paragraphs'][]=germanText($p);
foreach(['pages'=>$result,'sources'=>$sources,'home'=>$home,'about'=>$about] as $name=>$data)file_put_contents($base.'de/'.$name.'.json',json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
echo count($result)." German pages imported; ".count($home['features'])." homepage features.\n";
