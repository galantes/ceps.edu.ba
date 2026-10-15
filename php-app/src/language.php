<?php
declare(strict_types=1);

function isEnglish(): bool {
    return (bool)preg_match('~^/en(?:/|$)~i', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')
        || strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]) === 'en.ceps.edu.ba';
}
function isGerman(): bool {
    return (bool)preg_match('~^/de(?:/|$)~i', parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/')
        || strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]) === 'de.ceps.edu.ba';
}
function languagePath(string $path): string {
    return preg_replace('~^/(?:en|de)(?=/|$)~i', '', $path) ?: '/';
}
function siteLanguage(): string { return isGerman() ? 'de' : (isEnglish() ? 'en' : 'bs'); }
function languageCode(bool|string|null $language = null): string {
    return is_bool($language) ? ($language ? 'en' : 'bs') : ($language ?? siteLanguage());
}
function languagePrefix(string $path, bool|string|null $english = null): string {
    $language=languageCode($english);
    return $language === 'bs' ? $path : '/'.$language.($path === '/' ? '' : $path);
}
function newsLanguageColumn(): string { return isGerman() ? 'De' : (isEnglish() ? 'En' : 'Hr'); }
function germanCopy(string $name): array {
    static $copy=[];
    return $copy[$name] ??= json_decode(file_get_contents(dirname(__DIR__).'/content/de/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);
}
function localizedCopy(string $name): array { return isGerman() ? germanCopy($name) : englishCopy($name); }
function germanPageAvailable(string $path): bool {
    $key=strtolower(preg_replace('/\.aspx$/i','',ltrim($path,'/')));
    return in_array($key,['','onama','novosti','pretraga'],true) || isset(germanCopy('pages')[$key]);
}
function englishCopy(string $name): array {
    return json_decode(file_get_contents(dirname(__DIR__).'/content/en/'.$name.'.json'), true, 512, JSON_THROW_ON_ERROR);
}
function translate(string $value): string {
    if (siteLanguage() === 'bs') return $value;
    static $copy;
    $copy ??= array_replace(localizedCopy('interface'), localizedCopy('translations'));
    $key = trim(preg_replace('/\s+/u', ' ', $value));
    if(str_ends_with($key,' – Za one koji žele više!'))return substr($key,0,-strlen(' – Za one koji žele više!'));
    if (isset($copy[$key])) return str_replace(trim($value), $copy[$key], $value);
    if(isGerman() && preg_match('~^Nostrifikacija diplome u (.+?)( \([^)]+\))? - (\d{4})$~u',$key,$m)) {
        $countries=['Njemačkoj'=>'Deutschland','Hrvatskoj'=>'Kroatien','Sloveniji'=>'Slowenien','Srbiji'=>'Serbien','Crnoj Gori'=>'Montenegro'];
        return 'Anerkennung des Diploms in '.($countries[$m[1]] ?? $m[1]).(isset($m[2])?str_replace('Cestovni promet','Straßenverkehr',$m[2]):'').' – '.$m[3];
    }
    $patterns = [
        '~^Stranica (\d+) od (\d+)$~u'=>'Page $1 of $2',
        '~^(\d+) objava u arhivi$~u'=>'$1 posts in the archive',
        '~^Sve ranije objave \((\d+)\)$~u'=>'All older posts ($1)',
        '~^Objave \((\d+)\)$~u'=>'Posts ($1)',
        '~^Novosti i objave — stranica (\d+)$~u'=>'News — page $1',
        '~^Pročitaj: (.+)$~u'=>'Read: $1',
        '~^Pokreni video: (.+)$~u'=>'Play video: $1',
        '~^Slajd (\d+): (.+)$~u'=>'Slide $1: $2',
        '~^(\d+) od (\d+)$~u'=>'$1 of $2',
    ];
    if (isGerman()) $patterns = [
        '~^Stranica (\d+) od (\d+)$~u'=>'Seite $1 von $2',
        '~^(\d+) objava u arhivi$~u'=>'$1 Beiträge im Archiv',
        '~^Sve ranije objave \((\d+)\)$~u'=>'Alle älteren Beiträge ($1)',
        '~^Objave \((\d+)\)$~u'=>'Beiträge ($1)',
        '~^Novosti i objave — stranica (\d+)$~u'=>'Nachrichten — Seite $1',
        '~^Pročitaj: (.+)$~u'=>'Lesen: $1',
        '~^Pokreni video: (.+)$~u'=>'Video abspielen: $1',
        '~^Slajd (\d+): (.+)$~u'=>'Folie $1: $2',
        '~^(\d+) od (\d+)$~u'=>'$1 von $2',
    ];
    foreach ($patterns as $pattern=>$replacement) if (preg_match($pattern,$key)) return preg_replace($pattern,$replacement,$key);
    return $value;
}
/** Translate interface text only; stored news, documents and official names stay intact. */
function englishInterface(string $html): string {
    if (siteLanguage() === 'bs') return $html;
    $doc = new DOMDocument('1.0','UTF-8');
    $previous=libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">'.$html,LIBXML_NONET);
    libxml_clear_errors();libxml_use_internal_errors($previous);
    $doc->documentElement->setAttribute('lang',siteLanguage());
    if(isGerman())foreach($doc->getElementsByTagName('a') as $link) {
        if($link->getAttribute('href')==='https://www.ceps.edu.ba/Files/2026/04/letak_2026_web.pdf')$link->setAttribute('href','/Files/PDF/Letak_de.pdf');
    }
    $xpath=new DOMXPath($doc);
    foreach ($xpath->query('//text()[not(ancestor::script) and not(ancestor::style) and not(ancestor::textarea) and not(ancestor::*[contains(concat(" ",normalize-space(@class)," ")," article-body ")]) and not(ancestor::*[contains(concat(" ",normalize-space(@class)," ")," agreement-links ")])]') as $node) {
        $node->nodeValue=translate($node->nodeValue);
    }
    foreach ($doc->getElementsByTagName('*') as $node) foreach (['aria-label','title','placeholder','alt','content'] as $attribute) {
        if ($node->hasAttribute($attribute)) $node->setAttribute($attribute,translate($node->getAttribute($attribute)));
    }
    foreach(iterator_to_array($doc->childNodes) as $node) if($node->nodeType===XML_PI_NODE)$doc->removeChild($node);
    return $doc->saveHTML();
}
function siteOrigin(): string {
    $origin=rtrim(getenv('CEPS_ORIGIN') ?: 'https://ceps.edu.ba','/');
    return $origin === 'https://www.ceps.edu.ba' ? 'https://ceps.edu.ba' : $origin;
}
function languageSwitchUrl(bool|string $english): string {
    $language=languageCode($english);
    $path=languagePath(parse_url($_SERVER['REQUEST_URI'] ?? '/',PHP_URL_PATH) ?: '/');
    $internal=seoInternalPath($path);
    if (preg_match('~^/(?:news|novosti|nachrichten)/(\d+)~i',$path,$m) || strcasecmp($internal,'/Novost')===0) {
        $id=$m[1] ?? (array_change_key_case($_GET,CASE_LOWER)['id'] ?? '');
        $q=database()->prepare('SELECT Hr,En,De FROM Novosti WHERE ID=?');$q->execute([$id]);$row=$q->fetch();
        $column=['bs'=>'Hr','en'=>'En','de'=>'De'][$language];
        if (!$row || !$row[$column]) return seoUrl('/Novosti',$language);
        return seoUrl('/Novost?id='.(int)$id,$language);
    }
    if($language==='de' && !germanPageAvailable($internal)) return '/de';
    $query=isset($_GET['q']) && is_string($_GET['q']) ? '?q='.rawurlencode($_GET['q']) : '';
    return seoUrl(languagePrefix($internal,$language).$query,$language);
}
function languageAlternateTags(string $canonical): string {
    if(isset($_GET['page']) && (int)$_GET['page']>1)return '<link rel="alternate" hreflang="'.siteLanguage().'" href="'.esc(siteOrigin().$canonical).'">';
    $languages=['bs','en','de'];
    if (preg_match('~/(?:news|novosti|nachrichten)/(\d+)~',$canonical,$m)) {
        $q=database()->prepare('SELECT Hr,En,De FROM Novosti WHERE ID=?');$q->execute([$m[1]]);$row=$q->fetch();
        $languages=array_keys(array_filter(['bs'=>$row['Hr'] ?? false,'en'=>$row['En'] ?? false,'de'=>$row['De'] ?? false]));
    } elseif(!germanPageAvailable(seoInternalPath(languagePath(parse_url($canonical,PHP_URL_PATH) ?: '/')))) {
        $languages=['bs','en'];
    }
    $html='';
    foreach($languages as $language)$html.='<link rel="alternate" hreflang="'.$language.'" href="'.esc(siteOrigin().languageSwitchUrl($language)).'">';
    if(in_array('bs',$languages,true))$html.='<link rel="alternate" hreflang="x-default" href="'.esc(siteOrigin().languageSwitchUrl('bs')).'">';
    return $html;
}
function legacyEnglishRedirect(): void {
    $host=strtolower(explode(':',$_SERVER['HTTP_HOST'] ?? '')[0]);
    if (!in_array($host,['en.ceps.edu.ba','de.ceps.edu.ba'],true)) return;
    $language=$host==='de.ceps.edu.ba'?'de':'en';
    $uri=$_SERVER['REQUEST_URI'] ?? '/';
    $path=parse_url($uri,PHP_URL_PATH) ?: '/';
    if (preg_match('~^/(?:Files|App_Themes|assets|media)(?:/|$)~i',$path)) $target=seoUrl($uri,false);
    else $target=seoUrl(languagePrefix(languagePath($path),$language).(isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING']!==''?'?'.$_SERVER['QUERY_STRING']:''),$language);
    header('Location: https://ceps.edu.ba'.$target,true,301);exit;
}

function englishExtraFile(string $path): ?array {
    static $files;
    $files ??= englishCopy('files');
    foreach($files as $canonical=>$info) if(strcasecmp('/'.$canonical,rawurldecode($path))===0) return ['path'=>'/'.$canonical,'file'=>dirname(__DIR__).'/content/en/files/'.basename($info['file'])];
    foreach(germanCopy('files') as $canonical=>$info) if(strcasecmp('/'.$canonical,rawurldecode($path))===0) return ['path'=>'/'.$canonical,'file'=>dirname(__DIR__).'/content/de/files/'.basename($info['file'])];
    return null;
}
