<?php
declare(strict_types=1);
function seoSlug(string $value): string {
    $value=html_entity_decode(strip_tags($value),ENT_QUOTES,'UTF-8');
    $value=strtr($value,['č'=>'c','ć'=>'c','š'=>'s','ž'=>'z','đ'=>'d','Č'=>'C','Ć'=>'C','Š'=>'S','Ž'=>'Z','Đ'=>'D']);
    $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value);
    return trim(preg_replace('/[^a-z0-9]+/','-',strtolower($ascii ?: $value)),'-') ?: 'objava';
}
function seoRoutes(bool|string|null $english=null): array {
    $english=languageCode($english);
    static $cache=[];
    if(isset($cache[$english]))return $cache[$english];
    $names=['Onama'=>'o-nama','Misija'=>'misija-vizija-i-ciljevi','Kvaliteta'=>'osiguranje-kvalitete','Knjiznica'=>'knjiznica-i-izdavastvo','DIT'=>'drustvena-i-tehnicka-istrazivanja','Cisco'=>'cisco-akademija','Zgrada'=>'nova-zgrada','Medunarodna_suradnja'=>'medunarodna-suradnja','360-virtual-tour'=>'virtualna-setnja','StudijIT'=>'studiji/informacijske-tehnologije','StudijPE'=>'studiji/poslovna-ekonomija','StudijPromet'=>'studiji/promet','StudijEnergetika'=>'studiji/energetika','StudijSigurnost'=>'studiji/sigurnost','StudijZdravstvo'=>'studiji/zdravstvene-studije','StudijZastita'=>'studiji/zastita-na-radu-i-zastita-od-pozara','StudijEng'=>'studiji/business-economics','pravne-nauke'=>'studiji/pravne-nauke'];
    if($english!=='bs')$names=array_replace($names,$english==='de'?germanCopy('routes'):englishCopy('routes'));
    $routes=['/'=>'/','/Onama'=>$english==='de'?'/ueber-uns':($english==='en'?'/about':'/o-nama'),'/Novosti'=>$english==='de'?'/nachrichten':($english==='en'?'/news':'/novosti'),'/Pretraga'=>$english==='de'?'/suche':($english==='en'?'/search':'/pretraga')];
    foreach(['pages','projects'] as $file)foreach(json_decode(file_get_contents(dirname(__DIR__).'/content/'.$file.'.json'),true) as $page){
        $slug=$page['slug'];$new=$names[$slug] ?? implode('/',array_map('seoSlug',explode('/',str_replace('_','-',$slug))));
        if(str_starts_with($new,'projekt/'))$new=($english==='de'?'projekte/':($english==='en'?'projects/':'projekti/')).substr($new,8);
        $routes['/'.$slug]='/'.$new;
    }
    return $cache[$english]=$routes;
}
function seoNewsRoutes(bool|string|null $english=null): array {
    $english=languageCode($english);static $cache=[];
    if(isset($cache[$english]))return $cache[$english];
    $routes=[];
    foreach(database()->query('SELECT ID,Naslov FROM Novosti'.($english==='bs'?'':' WHERE '.($english==='de'?'De':'En').'=1')) as $row)$routes[(string)$row['ID']]=($english==='de'?'/nachrichten/':($english==='en'?'/news/':'/novosti/')).$row['ID'].'-'.seoSlug($row['Naslov']);
    return $cache[$english]=$routes;
}
function seoUrl(string $url,bool|string|null $english=null): string {
    if($url==='' || $url[0]==='#')return $url;
    $parts=parse_url($url);
    if($parts===false || (!isset($parts['path']) && !isset($parts['host'])))return $url;
    if(isset($parts['host']) && !in_array(strtolower($parts['host']),['ceps.edu.ba','www.ceps.edu.ba','en.ceps.edu.ba','de.ceps.edu.ba'],true))return $url;
    if(isset($parts['scheme']) && !in_array(strtolower($parts['scheme']),['http','https'],true))return $url;
    $path='/'.ltrim($parts['path'] ?? '/','/');
    $explicitLanguage=null;
    if(preg_match('~^/(en|de)(?:/|$)~i',$path,$languageMatch))$explicitLanguage=strtolower($languageMatch[1]);
    elseif(in_array(strtolower($parts['host'] ?? ''),['en.ceps.edu.ba','de.ceps.edu.ba'],true))$explicitLanguage=substr(strtolower($parts['host']),0,2);
    $english=languageCode($english ?? $explicitLanguage);
    $path=languagePath($path);
    foreach(englishCopy('file-redirects') as $old=>$new) if(strcasecmp(rawurldecode($path),$old)===0) return '/'.implode('/',array_map('rawurlencode',explode('/',ltrim($new,'/')))).(isset($parts['query'])?'?'.$parts['query']:'').(isset($parts['fragment'])?'#'.$parts['fragment']:'');
    if(englishExtraFile($path))return $path.(isset($parts['query'])?'?'.$parts['query']:'').(isset($parts['fragment'])?'#'.$parts['fragment']:'');
    if(preg_match('~^/(?:Files|assets|App_Themes|media|admin)(?:/|$)~i',$path))return isset($parts['host']) && in_array(strtolower($parts['host']),['en.ceps.edu.ba','de.ceps.edu.ba'],true) ? 'https://ceps.edu.ba'.$path.(isset($parts['query'])?'?'.$parts['query']:'') : $url;
    $key=strtolower(preg_replace('/\.aspx$/i','',rtrim($path,'/'))) ?: '/';
    if($key==='/knowledge-hub-for-climate-and-sustainability')$key='/centar-za-klimu-i-odrzivost';
    $query=$parts['query'] ?? '';$target=null;
    if(in_array($key,['/default','/'],true))$target='/';
    foreach(seoRoutes($english) as $old=>$new)if($key===strtolower($old) || $key===$new){$target=$new;break;}
    // Legacy content sometimes links to the Bosnian friendly path.
    if($target===null)foreach(seoRoutes(false) as $old=>$new)if($key===$new){$target=seoRoutes($english)[$old];break;}
    if($key==='/novost'){
        parse_str($query,$params);$id=array_change_key_case($params,CASE_LOWER)['id'] ?? null;
        if(is_string($id)&&ctype_digit($id))$target=seoNewsRoutes($english)[(string)(int)$id] ?? null;
        if(!$target && $english!=='bs' && is_string($id) && ctype_digit($id)) { $target=seoNewsRoutes(false)[(string)(int)$id] ?? null; if($target)$english='bs'; }
        if($target){foreach(array_keys($params) as $name)if(strtolower((string)$name)==='id')unset($params[$name]);$query=http_build_query($params);}
    }elseif(preg_match('~^/(?:news|novosti|nachrichten)/([0-9]+)(?:-.*)?$~',$key,$m)){
        $target=seoNewsRoutes($english)[(string)(int)$m[1]] ?? null;
        if(!$target && $english!=='bs'){$target=seoNewsRoutes(false)[(string)(int)$m[1]] ?? null;if($target)$english='bs';}
    }
    // A German URL is only published when its German source exists.
    if($english==='de' && $target!==null && !str_starts_with($target,'/nachrichten/')) {
        foreach(seoRoutes('de') as $old=>$new)if($new===$target && !germanPageAvailable($old)){$target=seoRoutes(false)[$old];$english='bs';break;}
    }
    return $target===null ? ($explicitLanguage ? languagePrefix($path,$english).($query!==''?'?'.$query:'') : $url) : languagePrefix($target,$english).($query!==''?'?'.$query:'').(isset($parts['fragment'])?'#'.$parts['fragment']:'');
}
function seoInternalPath(string $path): string {
    $key=strtolower(rtrim(languagePath($path),'/')) ?: '/';
    foreach(seoRoutes() as $old=>$new)if($key===$new)return $old;
    return languagePath($path);
}
