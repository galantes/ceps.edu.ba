<?php
declare(strict_types=1);
require_once __DIR__ . '/shell.php';
require_once __DIR__ . '/related-pages.php';
function contentPages(): array {
    static $pages;
    if ($pages !== null) return $pages;
    $pages = array_merge(json_decode(file_get_contents(dirname(__DIR__) . '/content/pages.json'), true, 512, JSON_THROW_ON_ERROR), json_decode(file_get_contents(dirname(__DIR__) . '/content/projects.json'), true, 512, JSON_THROW_ON_ERROR));
    if (siteLanguage() !== 'bs') foreach (localizedCopy('pages') as $key=>$translation) if(isset($pages[$key])) $pages[$key] = array_replace($pages[$key], $translation);
    if (isGerman()) $pages=array_intersect_key($pages,germanCopy('pages'));
    return $pages;
}
function contentPage(array $page): string {
    if ($page['slug'] === 'Kontakt') { require_once __DIR__.'/contact-page.php'; return contactPage(); }
    if ($page['slug'] === 'Projekti' || str_starts_with($page['slug'], 'Projekt/')) { require_once __DIR__.'/projects.php'; return projectPage($page); }
    $html = $page['html'];
    if ($page['slug'] === 'Studiji') {
        $html = preg_replace('~<img\b[^>]*src="https://ceps\.edu\.ba/Files/Images/studiji\.jpg"[^>]*>~i', '', $html, 1);
    }
    if ($page['slug'] === 'Placanje-u-ratama') {
        $html = preg_replace('~<img\b[^>]*src="https://ceps\.edu\.ba/Files/Images/2492020\.jpg"[^>]*>~i', '', $html, 1);
    }
    if ($page['slug'] === 'Medunarodna_suradnja') {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);
        $heading = $xpath->query('//h2[normalize-space(.)="Ugovori i sporazumi" or normalize-space(.)="Agreements and contracts"]')->item(0);
        if ($heading) {
            $source = '';
            for ($node = $heading->nextSibling; $node; $node = $node->nextSibling) $source .= $doc->saveHTML($node);
            // Legacy entries span several wrapper divs; collect the entire section.
            $source = strip_tags($source, '<b><br><a>');
            preg_match_all('~<b>(.*?)</b>(.*?)(?=<b>|$)~s', $source, $entries, PREG_SET_ORDER);
            $cards = $doc->createElement('div');
            $cards->setAttribute('class', 'cooperation-agreement-cards');
            foreach ($entries as $entry) {
                $card = $doc->createElement('article');
                $card->setAttribute('class', 'cooperation-agreement-card');
                $name = $doc->createElement('p');
                $name->setAttribute('class', 'agreement-name');
                $name->appendChild($doc->createTextNode(html_entity_decode(strip_tags($entry[1]), ENT_QUOTES, 'UTF-8')));
                $details = $doc->createElement('div');
                $details->setAttribute('class', 'agreement-contact');
                $lines = preg_split('~<br\s*/?>~i', $entry[2]);
                foreach ($lines as $line) {
                    $text = trim(html_entity_decode(strip_tags($line), ENT_QUOTES, 'UTF-8'), " \n\r\t,");
                    if ($text === '') continue;
                    if ($details->hasChildNodes()) $details->appendChild($doc->createElement('br'));
                    $details->appendChild($doc->createTextNode($text));
                }
                $card->appendChild($name); $card->appendChild($details); $cards->appendChild($card);
            }
            while ($heading->nextSibling) $heading->parentNode->removeChild($heading->nextSibling);
            $heading->parentNode->appendChild($cards);
            $html = ''; foreach ($doc->getElementsByTagName('body')->item(0)->childNodes as $node) $html .= $doc->saveHTML($node);
        }

        $html = preg_replace_callback('~(<h2>(?:Projekti i konferencije|Projects and conferences)</h2>)\s*<p>(.*?)</p>~s', function ($section) {
            preg_match_all('~<a\s+href="([^"]*)"[^>]*>(.*?)</a>~s', $section[2], $links, PREG_SET_ORDER);
            $list = '<ul class="cooperation-resource-list">';
            foreach ($links as $link) {
                $label = preg_replace('/^[\s·•]+/u', '', trim(strip_tags($link[2])));
                $list .= '<li><a href="'.$link[1].'">'.icon('link').'<span>'.esc($label).'</span></a></li>';
            }
            return $section[1].$list.'</ul>';
        }, $html);
    }

    if ($page['slug'] === 'Galerija') {
        require_once __DIR__.'/ceps-tv.php';
        $html = str_replace('<h4>CEPS TV</h4>', '<h4>CEPS TV</h4>'.cepsTv(), $html);
    }
    if ($page['slug'] === 'Studiji') {
        $html = preg_replace_callback('~<p>\s*(<a\b[^>]*>📁 Nastavni planovi i programi</a>)\s*(<a\b[^>]*>📁 Ishodi učenja</a>)\s*</p>~u', function ($match) {
            $plans = str_replace(['<a ', '📁 '], ['<a class="study-resource-link" ', icon('folders').' <span>'], $match[1]);
            $plans = str_replace('</a>', '</span></a>', $plans);
            $outcomes = str_replace(['<a ', '📁 '], ['<a class="study-resource-link" ', ''], $match[2]);
            return '<div class="study-resource-links">'.$plans.$outcomes.'</div>';
        }, $html);
    }
    if ($page['slug'] === 'DIT') {
        $html = preg_replace('~<a\b[^>]*href="https?://creativecommons\.org/licenses/by-nc/4\.0/"[^>]*>\s*<img\b[^>]*>\s*</a>\s*<br\s*/?>\s*Ovaj.*?<a\b[^>]*href="https?://creativecommons\.org/licenses/by-nc/4\.0/"[^>]*>.*?</a>\s*\.~s', '<div class="dit-license"><a href="https://creativecommons.org/licenses/by-nc/4.0/"><img src="https://i.creativecommons.org/l/by-nc/4.0/88x31.png" alt="Creative Commons BY-NC" width="88" height="31"></a><p>Ovaj časopis je licenciran pod <a href="https://creativecommons.org/licenses/by-nc/4.0/">Creative Commons Attribution-NonCommercial 4.0 International License</a>.</p></div>', $html, 1);
        $html = preg_replace_callback('~<div>\s*<img\b[^>]*src="[^"]*/diti_naslovnica\.jpg".*?</div>\s*<table>.*?</table>~s', function ($match) {
            preg_match_all('~<img\b[^>]*src="([^"]+)"[^>]*>~', $match[0], $images);
            $sources = array_values(array_unique($images[1]));
            $cover = array_shift($sources);
            $names = ['inde_copernicus.jpg'=>'Index Copernicus', 'road.jpg'=>'ROAD', 'erih_plus.jpg'=>'ERIH PLUS', 'EBSCO_logo.png'=>'EBSCO', 'abes_logo.gif'=>'ABES', 'zdb-1.png'=>'ZDB', 'ebsco.png'=>'EBSCO'];
            $intro = '<section class="dit-intro"><img class="dit-cover" src="'.esc($cover).'" alt="Naslovnica časopisa Društvena i tehnička istraživanja" loading="lazy"><div class="dit-indexing"><div class="dit-logos">';
            foreach ($sources as $src) $intro .= '<img src="'.esc($src).'" alt="'.esc($names[basename($src)] ?? 'Baza podataka').'" loading="lazy">';
            return $intro.'</div><p>Radovi objavljeni u časopisu Društvena i tehnička istraživanja referiraju se u sljedećim bazama podataka:</p><ul class="dit-databases"><li>EBSCO</li><li>Index Copernicus International</li><li>CEEOL (Central and Eastern European Online Library)</li><li>ERIH PLUS</li><li>ZDB</li><li>SUDOC</li></ul></div></section>';
        }, $html, 1);
    }
    if ($page['slug'] === 'Kvaliteta') {
        // The legacy document list contains stray paragraphs and line breaks inside items.
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);
        libxml_clear_errors(); libxml_use_internal_errors($previous);
        foreach ($doc->getElementsByTagName('h2') as $heading) {
            if (trim($heading->textContent) !== (isEnglish()?'Documents':'Dokumenti')) continue;
            $container = $heading->parentNode;
            $list = $doc->createElement('ul');
            $list->setAttribute('class', 'quality-documents');
            foreach (iterator_to_array($container->getElementsByTagName('a')) as $source) {
                $link = $source->cloneNode(false);
                $link->setAttribute('class', 'document-link');
                $link->appendChild($doc->createTextNode(trim(preg_replace('/\s+/u', ' ', $source->textContent))));
                if (!preg_match('~\.pdf$~i', parse_url($link->getAttribute('href'), PHP_URL_PATH) ?: '')) {
                    $icon = $doc->createElement('i');
                    $icon->setAttribute('class', 'ti ti-file-description');
                    $icon->setAttribute('aria-hidden', 'true');
                    $link->insertBefore($icon, $link->firstChild);
                }
                $item = $doc->createElement('li'); $item->appendChild($link); $list->appendChild($item);
            }
            foreach (iterator_to_array($container->childNodes) as $child) if ($child !== $heading) $container->removeChild($child);
            $container->appendChild($list);
            break;
        }
        $html = ''; foreach ($doc->getElementsByTagName('body')->item(0)->childNodes as $child) $html .= $doc->saveHTML($child);
    }
    if (in_array($page['slug'], ['Kontakt','360-virtual-tour'], true)) {
        $html = preg_replace_callback('~<a href="([^"]+)">(?:Otvori galeriju, video ili prilog|Open gallery, video or attachment)</a>~', function($m) {
            $url = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            $map = str_starts_with($url, 'https://www.google.com/maps/embed?');
            $form = in_array($url, ['https://www.ceps.edu.ba/Mail/form.php','/Mail/form.php'], true);
            if ($form) $url = 'https://www.ceps.edu.ba/Mail/form.php';
            if (!$map && !$form) return $m[0];
            return '<iframe class="content-embed '.($form?'contact-form':'map-frame').'" src="'.esc($url).'" title="'.($form?'Pošaljite upit CEPS-u':'Lokacija CEPS-a').'" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>';
        }, $html);
    }
    if ($page['slug'] === '360-virtual-tour') {
        $html = preg_replace_callback('~<iframe\b.*?</iframe>~s', function ($match) {
            return '<div class="virtual-tour"><div class="tour-toolbar"><button type="button" class="tour-fullscreen">'.icon('maximize').'<span>Cijeli ekran</span></button></div>'.$match[0].'</div>';
        }, $html);
    }
    foreach (['Konkursi','Aktivnosti','projekt_15'] as $column) {
        if (!str_contains($html, '{{'.$column.'}}')) continue;
        $rows = database()->query('SELECT ID,Naslov,Datum,Tekst FROM Novosti WHERE '.newsLanguageColumn().'=1 AND '.$column.'=1 ORDER BY Datum DESC,ID DESC')->fetchAll();
        $notices = $page['slug'] === 'Medunarodna_suradnja' && $column === 'Konkursi';
        $cards = $page['slug'] === 'Medunarodna_suradnja' && !$notices;
        $listClass = $notices ? 'programme-news cooperation-notices' : ($cards ? 'programme-news cooperation-news-grid' : 'programme-news');
        $list = '<ul class="'.$listClass.'">';
        foreach ($rows as $index => $row) {
            if ($index === 10) $list .= '</ul><details class="older-posts"><summary>Sve ranije objave (' . (count($rows)-10) . ')</summary><ul class="'.$listClass.'">';
            $url = '/Novost?id='.(int)$row['ID'];
            if ($notices) {
                $list .= '<li><a href="'.$url.'">'.icon('speakerphone').'<span class="notice-title">'.esc(titleText($row['Naslov'])).'</span><time>'.esc(newsDate($row['Datum'])).'</time>'.icon('arrow-up-right').'</a></li>';
            } elseif ($cards) {
                $list .= '<li class="cooperation-news-card"><a class="cooperation-news-picture" href="'.$url.'" tabindex="-1" aria-hidden="true"><img class="news-cover" src="'.esc(newsImage($row['Tekst'])).'" data-fallback="'.esc(NEWS_DEFAULT_IMAGE).'" alt="" loading="lazy"></a><div class="cooperation-news-copy"><p class="cooperation-news-title"><a href="'.$url.'">'.esc(titleText($row['Naslov'])).'</a></p><div class="cooperation-news-meta"><time>'.esc(newsDate($row['Datum'])).'</time><a class="cooperation-news-more" href="'.$url.'">Pročitaj više '.icon('arrow-up-right').'</a></div></div></li>';
            } else {
                $list .= '<li><time>'.esc(newsDate($row['Datum'])).'</time><a href="'.$url.'">'.esc(titleText($row['Naslov'])).'</a></li>';
            }
        }
        $list .= '</ul>' . (count($rows)>10 ? '</details>' : '');
        $html = str_replace('{{'.$column.'}}', $list, $html);
    }
    foreach (['privreda','skolstvo'] as $category) {
        if (!str_contains($html, '{{'.$category.'}}')) continue;
        $stmt = database()->prepare('SELECT Naziv,Putanja FROM Sporazumi WHERE Aktivan=1 AND Kategorija=? ORDER BY Sort DESC');
        $stmt->execute([$category]);
        $list = '<ul class="agreement-links">';
        foreach($stmt as $row) {
            $url = localUrl($row['Putanja'] ?? '');
            if ($url && safeUrl($url)) $list .= '<li><a href="'.esc($url).'">'.icon('file-description').' '.esc($row['Naziv']).'</a></li>';
        }
        $html = str_replace('{{'.$category.'}}', $list.'</ul>', $html);
    }
    $isStudy = preg_match('/^Studij(?!i$)/', $page['slug']) || $page['slug'] === 'pravne-nauke';
    if ($isStudy) {
        $html = preg_replace('~<h[1-6]\b[^>]*>\s*(?:Novosti|News)\s*</h[1-6]>~iu', '', $html);
    }
    $pages = contentPages();
    $html = preg_replace_callback('~href="https?://(?:www\.)?ceps\.edu\.ba/([^"?#]+)([^" ]*)"~i', function($m) use($pages) {
        $key = strtolower(preg_replace('/\.aspx$/i', '', $m[1]));
        return isset($pages[$key]) ? 'href="/' . $pages[$key]['slug'] . $m[2] . '"' : $m[0];
    }, $html);
    if (str_contains($html, '{{external-form}}')) {
        $html = preg_replace('~<h[1-6]>\s*' . preg_quote(esc($page['title']), '~') . '\s*</h[1-6]>~s', '', $html, 1);
        $formIds = ['Alumni'=>1, 'Maturanti'=>4, 'prijava-andragoska-obuka'=>3];
        $formId = $formIds[$page['slug']] ?? null;
        if ($formId) {
            $url = 'https://www.cognitoforms.com/f/OaESQGtDYUCNcvcssX6W9Q/' . $formId;
            if (in_array($page['slug'], ['Alumni','Maturanti'], true)) {
                $formTitle = $page['slug'] === 'Alumni' ? 'Pridružite se Alumni asocijaciji' : 'Prijava na besplatnu radionicu za maturante';
                if ($page['slug'] === 'Maturanti') $html = preg_replace('~<h[1-6]>\s*Prijava na besplatnu radionicu za maturante\s*</h[1-6]>~u', '', $html);
                $html = str_replace('{{external-form}}', '<section class="alumni-application"><h3>'.esc($formTitle).'</h3><iframe id="'.strtolower($page['slug']).'-cognito" class="alumni-form-frame" src="'.esc($url).'" title="'.esc($formTitle).'" height="1100" width="100%"></iframe><script src="https://www.cognitoforms.com/f/iframe.js"></script><p class="alumni-form-help">Ako se obrazac ne prikazuje, <a href="'.esc($url).'" target="_blank" rel="noopener noreferrer">otvorite ga u novoj kartici</a>.</p></section>', $html);
            }
            $html = str_replace('{{external-form}}', '<div class="application-form"><div class="application-symbol">'.icon('forms').'</div><h3>Prijavni obrazac</h3><p>Otvorite službeni CEPS obrazac i unesite svoje podatke. Obrazac se otvara u novoj kartici.</p><a class="pill pill-yellow" href="'.esc($url).'" target="_blank" rel="noopener noreferrer"><span>Otvori obrazac</span>'.icon('external-link').'</a></div>', $html);
        }
    }
    $toc = []; $number = 0;
    $html = preg_replace_callback('~<h([1-6])>(.*?)</h\1>~s', function($m) use(&$toc,&$number) {
        $title = trim(strip_tags($m[2]));
        if (!$title || $title === '.') return '';
        $id = 'section-' . ++$number;
        $toc[] = ['id'=>$id,'title'=>$title];
        return '<h2 id="'.$id.'"'.($number === 1 ? ' class="content-first-heading"' : '').'>'.$m[2].'</h2>';
    }, $html);
    $heroImages = json_decode(file_get_contents(dirname(__DIR__) . '/content/hero-images.json'), true, 512, JSON_THROW_ON_ERROR);
    $studyImages = [];
    foreach ($heroImages as $url => $image) {
        $slug = pathinfo($url, PATHINFO_FILENAME);
        if ($slug !== 'Cisco') $studyImages[$slug] = $image;
    }
    $studyImages['pravne-nauke'] = '/assets/hero/law.webp';
    $otherImages = ['Cisco'=>'cisco.jpg', 'Placanje-u-ratama'=>'2492020.jpg', 'Studiji'=>'studiji.jpg', 'Upisi'=>'2492020.jpg', 'Maturanti'=>'studiji.jpg'];
    $studyImage = $studyImages[$page['slug']] ?? (isset($otherImages[$page['slug']]) ? 'https://ceps.edu.ba/Files/Images/' . $otherImages[$page['slug']] : null);
    $studyIcons = ['StudijPE'=>'chart-bar','StudijIT'=>'device-desktop-code','StudijZdravstvo'=>'medical-cross','StudijPromet'=>'road','StudijEnergetika'=>'bolt','StudijSigurnost'=>'shield-check','StudijZastita'=>'tools','StudijEng'=>'world','pravne-nauke'=>'scale'];
    ob_start(); ?>
    <div class="institution-page content-page" data-page="<?= esc($page['slug']) ?>" <?= $isStudy ? 'data-study' : '' ?> <?= $page['slug'] === 'Medunarodna_suradnja' ? 'data-cooperation' : '' ?>>
      <div class="institution-width breadcrumbs"><a href="/">Naslovnica</a><?= icon('chevron-right') ?><span><?= esc($page['title']) ?></span></div>
      <section class="content-page-heading institution-width<?= $studyImage ? ' study-photo-heading' : '' ?>"><?php if($studyImage): ?><img class="study-heading-image" src="<?= esc($studyImage) ?>" alt="" fetchpriority="high"><?php endif; ?><h1<?= $isStudy ? ' class="study-title"' : '' ?>><?php if ($isStudy): ?><?= icon($studyIcons[$page['slug']] ?? 'school') ?><?php endif; ?><span><?= esc($page['title']) ?></span></h1><?php if (!$isStudy): ?><span class="heading-rule"></span><?php endif; ?><?php if ($isStudy): ?><div class="study-page-actions"><a class="pill study-enrol-outline" href="/Upisi"><?= icon('school') ?><span>Informacije o upisu</span></a><a class="text-link" href="/Kontakt"><?= icon('message-circle') ?><span>Pitajte studentsku službu</span></a><a class="text-link" href="/Studiji"><?= icon('arrow-left') ?><span>Svi studiji</span></a></div><?php endif; ?></section>
      <div class="institution-layout institution-width"><?= relatedPagesSidebar($page, $toc) ?>
      <article class="institution-content legacy-content" id="page-content"><?= $html ?></article></div>
    </div>
    <?php return ob_get_clean();
}
