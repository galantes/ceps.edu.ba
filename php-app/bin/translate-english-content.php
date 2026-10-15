<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$base=dirname(__DIR__).'/content/';
$map=array_replace(json_decode(file_get_contents($base.'en/interface.json'),true),json_decode(file_get_contents($base.'en/translations.json'),true));
$extra=[
'1. PRIPREMA EU PROJEKATA'=>'1. PREPARING EU PROJECTS',
'3. WEB DIZAJN'=>'3. WEB DESIGN','4. UPRAVLJANJE OPERATIVNIM RIZICIMA'=>'4. OPERATIONAL RISK MANAGEMENT',
'Korisni linkovi'=>'Useful links','Saradnja sa privredom'=>'Cooperation with the economy','Ugovori i sporazumi'=>'Agreements and contracts',
'Abstrakt:'=>'Abstract:', 'Projektni tim'=>'Project team',
'· Strategija odnosa s javnošću visoke škole CEPS'=>'· CEPS public relations strategy',
'· Aplikacijska forma za dolazeće studente'=>'· Application form for incoming students',
'· Sporazumi o akademskoj saradnji - Visokoškolske institucije'=>'· Academic cooperation agreements – higher education institutions',
'· Antikoruptivni programi za jačanje integriteta visokoškolskih ustanova'=>'· Anti-corruption programmes for strengthening the integrity of higher education institutions',
'· Dani kriminalističkih nauka'=>'· Days of Criminal Justice Sciences',
'· Međunarodni standardi i uloga sigurnosnog sektora u borbi protiv korupcije'=>'· International standards and the role of the security sector in combating corruption',
'· Radionica o međunarodnim standardima i ulozi sigurnosnog sektora u borbi protiv korupcije'=>'· Workshop on international standards and the role of the security sector in combating corruption',
'· Prezentiranje sustava zaštite prijavitelja korupcije u BiH i borba protiv korupcije'=>'· Presentation of whistleblower protection in Bosnia and Herzegovina and combating corruption',
'XV Dani kriminalističkih nauka'=>'15th Days of Criminal Justice Sciences',
'· Program mobilnosti između studenata Evrope i BiH'=>'· Student mobility programme between Europe and Bosnia and Herzegovina',
'· Projekat stipendiranja studenata za studij u EU'=>'· Scholarships for study in the EU',
'· Njemački servis akademske razmjene'=>'· German Academic Exchange Service',
'· Fond Otvoreno drustvo BiH'=>'· Open Society Fund Bosnia and Herzegovina',
'· Iskustva 10000 studenata na vise od 1500 univerziteta'=>'· Experiences of 10,000 students at more than 1,500 universities',
'· Informacije o finasijskoj pomoći pri studiranju u inostranstvu'=>'· Information about financial support for studying abroad',
'· Portal sa informacijama o programima studija u inostranstvu'=>'· Information portal for study programmes abroad',
'· Češka republika'=>'· Czech Republic','· Njemačka'=>'· Germany','· Norveška'=>'· Norway','· Španjolska'=>'· Spain','· Švedska'=>'· Sweden','· Švicarska'=>'· Switzerland',
'Poziv za objavu radova u časopisu u okviru 1future projekta – Specijalno izdanje – Klima i održivost'=>'Call for papers within the 1FUTURE project – Special issue: Climate and Sustainability',
'Saglasnost ministarstva - Stručno osposobljavanje iz oblasti saobraćaja'=>'Ministry approval – vocational training in transport',
];
$map=array_replace($map,$extra);
file_put_contents($base.'en/translations.json',json_encode(array_replace(json_decode(file_get_contents($base.'en/translations.json'),true),$extra),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
function translatedNodeText(string $text):string {
 global $map;
 $key=trim(preg_replace('/\s+/u',' ',$text));
 if(isset($map[$key]))return preg_replace('/\S(?:.*\S)?/us',$map[$key],$text);
 $text=preg_replace('/\b([0-9]+) mjesec(?:a|i)?\b/u','$1 months',$text);
 return strtr($text,[
 '(Bosna i Hercegovina)'=>'(Bosnia and Herzegovina)', '(Crna Gora)'=>'(Montenegro)', '(Srbija)'=>'(Serbia)', '(Albanija)'=>'(Albania)', '(Italija)'=>'(Italy)', '(Španija)'=>'(Spain)', '(Slovenija)'=>'(Slovenia)', '(Hrvatska)'=>'(Croatia)', '(Sj. Makedonija)'=>'(North Macedonia)',
 ', Njemačka'=>', Germany', ', Švedska'=>', Sweden', ', Slovačka'=>', Slovakia', ', Srbija'=>', Serbia', ', Crna Gora'=>', Montenegro',
 ]);
}
function translatedHtml(string $html):string{
 if(trim($html)==='')return $html;
 $d=new DOMDocument('1.0','UTF-8');libxml_use_internal_errors(true);$d->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);libxml_clear_errors();$x=new DOMXPath($d);
 foreach($x->query('//text()[not(ancestor::script) and not(ancestor::style)]') as $n)$n->nodeValue=translatedNodeText($n->nodeValue);
 foreach($d->getElementsByTagName('*') as $n)foreach(['alt','title'] as $attr)if($n->hasAttribute($attr))$n->setAttribute($attr,translatedNodeText($n->getAttribute($attr)));
 foreach(json_decode(file_get_contents(dirname(__DIR__).'/content/en/file-redirects.json'),true) as $old=>$new)foreach($d->getElementsByTagName('a') as $link)$link->setAttribute('href',str_replace($old,$new,$link->getAttribute('href')));
 $s='';foreach($d->getElementsByTagName('body')->item(0)->childNodes as $n)$s.=$d->saveHTML($n);return $s;
}
$pages=json_decode(file_get_contents($base.'en/pages.json'),true);
foreach(json_decode(file_get_contents($base.'en/overrides.json'),true) as $key=>$override)$pages[$key]=array_replace($pages[$key],$override);
foreach($pages as &$p){$p['title']=translatedNodeText($p['title']);foreach(['html','resources'] as $field)if(isset($p[$field]))$p[$field]=translatedHtml($p[$field]);}
unset($p);
file_put_contents($base.'en/pages.json',json_encode($pages,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$home=json_decode(file_get_contents($base.'en/home.json'),true);$home['welcome']=array_map('translatedNodeText',$home['welcome']);foreach($home['programmes'] as &$p){$p['title']=translatedNodeText($p['title']);$key=strtolower(pathinfo($p['url'],PATHINFO_FILENAME));if(isset($pages[$key])){$d=new DOMDocument();$d->loadHTML('<?xml encoding="UTF-8">'.$pages[$key]['html'],LIBXML_NONET);$first=null;foreach($d->getElementsByTagName('p') as $candidate)if(trim($candidate->textContent)!==''){$first=$candidate;break;}if($first)$p['paragraphs']=[trim(preg_replace('/\s+/u',' ',$first->textContent))];}}unset($p);
// Match the existing English homepage's feature sections in their original order.
$source=($englishSourceDirectory ?? dirname(__DIR__,2).'/migration/output/en-source').'/Default.html';
if(is_file($source)){$d=new DOMDocument();$d->loadHTML('<?xml encoding="UTF-8">'.file_get_contents($source),LIBXML_NONET);$x=new DOMXPath($d);$features=[];
 foreach($x->query('//*[@id="Content"]//span[contains(@class,"title_programi")]') as $span){$paragraphs=[];for($n=$span->nextSibling;$n;$n=$n->nextSibling){if($n instanceof DOMElement && $n->tagName==='span' && str_contains($n->getAttribute('class'),'title_programi'))break;if($n instanceof DOMElement && $n->tagName==='p')$paragraphs[]=trim(preg_replace('/\s+/u',' ',$n->textContent));}if($paragraphs)$features[]=['title'=>trim(preg_replace('/\s+/u',' ',$span->textContent)),'paragraphs'=>$paragraphs];}
 if(count($features)>=10)$home['features']=$features;
}
foreach($home['documents'] as &$doc){$doc['title']=strtr($doc['title'],['Nostrifikacija diplome u Sloveniji'=>'Recognition of our diploma in Slovenia','Nostrifikacija diplome u Njemačkoj'=>'Recognition of our diploma in Germany','Nostrifikacija diplome u Hrvatskoj'=>'Recognition of our diploma in Croatia','Nostrifikacija diplome u Srbiji'=>'Recognition of our diploma in Serbia','Nostrifikacija diplome u Crnoj Gori'=>'Recognition of our diploma in Montenegro','Cestovni promet'=>'Road Traffic','Energetika'=>'Energetics','Škoda Erasmus+ praksa'=>'Škoda Erasmus+ traineeship','Certifikat - Sistem menadžmenta'=>'Certificate – Management system','Certifikat -'=>'Certificate –','Rješenje o akreditaciji'=>'Accreditation decision','Erasmus povelja u visokom obrazovanju'=>'Erasmus Charter for Higher Education','Natječaj/Konkurs za upis studenata u akademsku 2024/2025. godinu – drugi upisni rok'=>'Call for student admission in the 2024/2025 academic year – second admission period']);}unset($doc);
file_put_contents($base.'en/home.json',json_encode($home,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo 'English content translations applied; '.count($home['features'])." homepage features.\n";
