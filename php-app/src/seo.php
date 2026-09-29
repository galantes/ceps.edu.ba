<?php
declare(strict_types=1);

function seoSlug(string $value): string {
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES, 'UTF-8');
    $value = strtr($value, ['č'=>'c','ć'=>'c','š'=>'s','ž'=>'z','đ'=>'d','Č'=>'C','Ć'=>'C','Š'=>'S','Ž'=>'Z','Đ'=>'D']);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii ?: $value)), '-') ?: 'objava';
}
function seoRoutes(): array {
    static $routes;
    if ($routes !== null) return $routes;
    $names = ['Onama'=>'o-nama','Misija'=>'misija-vizija-i-ciljevi','Kvaliteta'=>'osiguranje-kvalitete','Knjiznica'=>'knjiznica-i-izdavastvo','DIT'=>'drustvena-i-tehnicka-istrazivanja','Cisco'=>'cisco-akademija','Zgrada'=>'nova-zgrada','Medunarodna_suradnja'=>'medunarodna-suradnja','360-virtual-tour'=>'virtualna-setnja','StudijIT'=>'studiji/informacijske-tehnologije','StudijPE'=>'studiji/poslovna-ekonomija','StudijPromet'=>'studiji/promet','StudijEnergetika'=>'studiji/energetika','StudijSigurnost'=>'studiji/sigurnost','StudijZdravstvo'=>'studiji/zdravstvene-studije','StudijZastita'=>'studiji/zastita-na-radu-i-zastita-od-pozara','StudijEng'=>'studiji/business-economics','pravne-nauke'=>'studiji/pravne-nauke'];
    $routes = ['/'=>'/','/Onama'=>'/o-nama','/Novosti'=>'/novosti','/Pretraga'=>'/pretraga'];
    foreach (['pages','projects'] as $file) {
        foreach (json_decode(file_get_contents(dirname(__DIR__).'/content/'.$file.'.json'), true) as $page) {
            $slug = $page['slug'];
            $new = $names[$slug] ?? implode('/', array_map('seoSlug', explode('/', str_replace('_', '-', $slug))));
            if (str_starts_with($new, 'projekt/')) $new = 'projekti/'.substr($new, 8);
            $routes['/'.$slug] = '/'.$new;
        }
    }
    return $routes;
}
function seoNewsRoutes(): array {
    static $routes;
    if ($routes !== null) return $routes;
    $routes = [];
    foreach (database()->query('SELECT ID,Naslov FROM Novosti') as $row) $routes[(string)$row['ID']] = '/novosti/'.$row['ID'].'-'.seoSlug($row['Naslov']);
    return $routes;
}
function seoUrl(string $url): string {
    if ($url === '' || $url[0] === '#') return $url;
    $parts = parse_url($url);
    if ($parts === false || (!isset($parts['path']) && !isset($parts['host']))) return $url;
    if (isset($parts['host']) && !in_array(strtolower($parts['host']), ['ceps.edu.ba','www.ceps.edu.ba'], true)) return $url;
    if (isset($parts['scheme']) && !in_array(strtolower($parts['scheme']), ['http','https'], true)) return $url;
    $path = '/'.ltrim($parts['path'] ?? '/', '/');
    $key = strtolower(preg_replace('/\.aspx$/i', '', rtrim($path, '/'))) ?: '/';
    $query = $parts['query'] ?? '';
    $target = null;
    if (in_array($key, ['/default','/'], true)) $target = '/';
    foreach (seoRoutes() as $old=>$new) if ($key === strtolower($old) || $key === $new) { $target = $new; break; }
    if ($key === '/novost') {
        parse_str($query, $params);
        $id = array_change_key_case($params, CASE_LOWER)['id'] ?? null;
        if (is_string($id) && ctype_digit($id)) $target = seoNewsRoutes()[(string)(int)$id] ?? null;
        if ($target) {
            foreach (array_keys($params) as $name) if (strtolower((string)$name) === 'id') unset($params[$name]);
            $query = http_build_query($params);
        }
    } elseif (preg_match('~^/novosti/([0-9]+)(?:-.*)?$~', $key, $match)) {
        $target = seoNewsRoutes()[(string)(int)$match[1]] ?? null;
    }
    return $target === null ? $url : $target.($query !== '' ? '?'.$query : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
}
function seoInternalPath(string $path): string {
    $key = strtolower(rtrim($path, '/')) ?: '/';
    foreach (seoRoutes() as $old=>$new) if ($key === $new) return $old;
    return $path;
}
