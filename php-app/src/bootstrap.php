<?php
declare(strict_types=1);

const SCHOOL_NAME = 'Visoka škola "CEPS - Centar za poslovne studije" Kiseljak';

function esc(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function database(): PDO {
    $dsn = getenv('CEPS_DSN');
    if (!$dsn) {
        // Local preview only. Production must explicitly configure MySQL.
        if (getenv('CEPS_ENV') === 'production') {
            throw new RuntimeException('CEPS_DSN is required in production.');
        }
        $path = dirname(__DIR__, 2) . '/migration/output/staging.sqlite';
        if (!is_file($path)) {
            throw new RuntimeException('Run migration/prepare.py first.');
        }
        $dsn = 'sqlite:' . $path;
    }
    $pdo = new PDO($dsn, getenv('CEPS_DB_USER') ?: '', getenv('CEPS_DB_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $pdo->exec('PRAGMA query_only = ON');
    }
    return $pdo;
}

function titleText(string $value): string {
    return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function excerpt(string $html): string {
    $text = preg_replace('/\s+/u', ' ', titleText($html)) ?? '';
    preg_match('/^.{0,170}/us', $text, $match);
    return ($match[0] ?? '') . (strlen($text) > strlen($match[0] ?? '') ? '…' : '');
}

function newsDate(?string $value): string {
    return $value ? (new DateTimeImmutable($value))->format('d. m. Y.') : '';
}

function safeUrl(string $value): bool {
    $value = preg_replace('/[\x00-\x20\x7f]/', '', $value) ?? '';
    $scheme = parse_url($value, PHP_URL_SCHEME);
    return $scheme === null || in_array(strtolower((string)$scheme), ['http', 'https', 'mailto', 'tel'], true);
}

function localUrl(string $value): string {
    if (str_starts_with($value, '~/')) $value = substr($value, 1);
    $parts = parse_url($value);
    if (is_array($parts) && in_array(strtolower($parts['host'] ?? ''), ['www.ceps.edu.ba', 'ceps.edu.ba'], true)) {
        $value = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
    }
    if (preg_match('#^(?:/|(?:\.?\./)*)(Files/.*)$#i', $value, $asset)) return 'https://ceps.edu.ba/' . $asset[1];
    return $value;
}

function articleSafeStyle(string $style): string {
    $safe=[];
    foreach(explode(';',$style) as $declaration){
        $pair=explode(':',$declaration,2);if(count($pair)!==2)continue;
        $key=strtolower(trim($pair[0]));$value=strtolower(trim($pair[1]));
        $allowed=match($key){
            'text-align'=>preg_match('/^(left|center|right|justify)$/D',$value),
            'font-weight'=>preg_match('/^(normal|bold|[1-9]00)$/D',$value),
            'font-style'=>preg_match('/^(normal|italic)$/D',$value),
            'text-decoration'=>preg_match('/^(none|underline|line-through|underline line-through)$/D',$value),
            'color','background-color'=>preg_match('/^(#[a-f0-9]{3,8}|[a-z]{3,20}|rgba?\([0-9.,% ]+\))$/D',$value),
            'font-size'=>preg_match('/^([1-9]|[1-6][0-9]|7[0-2])(px|pt)$/D',$value),
            'width','height','max-width'=>preg_match('/^(auto|[0-9]{1,4}(px|%))$/D',$value),
            'margin-left','margin-right'=>preg_match('/^(auto|0|[0-9]{1,3}(px|pt))$/D',$value),
            'border-collapse'=>in_array($value,['collapse','separate'],true),
            default=>false
        };
        if($allowed)$safe[]=$key.':'.$value;
    }
    return implode(';',$safe);
}

function articleHtml(string $html): string {
    // Keep source HTML unchanged in the database; sanitize only the rendered view.
    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $allowed = ['p','div','span','br','strong','b','em','i','u','s','h1','h2','h3','h4','h5','h6','ul','ol','li','a','img','figure','figcaption','blockquote','table','thead','tbody','tfoot','tr','th','td','hr','sup','sub'];
    $remove = ['script','style','iframe','object','embed','form','input','button','textarea','select','base','link','meta'];
    $nodes = iterator_to_array($doc->getElementsByTagName('*'));
    foreach (array_reverse($nodes) as $node) {
        $tag = strtolower($node->nodeName);
        if (in_array($tag, ['html','body','head'], true)) continue;
        if ($tag === 'iframe' && $node->hasAttribute('src') && safeUrl($node->getAttribute('src'))) {
            // Keep access to legacy galleries, videos and documents without
            // loading external embeds automatically in the preview.
            $paragraph = $doc->createElement('p');
            $link = $doc->createElement('a', 'Otvori galeriju, video ili prilog');
            $link->setAttribute('href', localUrl($node->getAttribute('src')));
            $paragraph->appendChild($link);
            $node->parentNode?->replaceChild($paragraph, $node);
            continue;
        }
        if (in_array($tag, $remove, true)) {
            $node->parentNode?->removeChild($node);
            continue;
        }
        if (!in_array($tag, $allowed, true)) {
            while ($node->firstChild) $node->parentNode?->insertBefore($node->firstChild, $node);
            $node->parentNode?->removeChild($node);
            continue;
        }
        foreach (iterator_to_array($node->attributes) as $attr) {
            if(strtolower($attr->name)==='style'){
                $style=articleSafeStyle($attr->value);
                if($style!=='')$node->setAttribute('style',$style);else $node->removeAttribute('style');
                continue;
            }
            $keep = in_array(strtolower($attr->name), ['href','src','alt','title','colspan','rowspan'], true);
            if (!$keep || (in_array($attr->name, ['href','src'], true) && !safeUrl($attr->value))) {
                $node->removeAttribute($attr->name);
            } elseif (in_array($attr->name, ['href','src'], true)) {
                $node->setAttribute($attr->name, localUrl($attr->value));
            }
        }
        if ($tag === 'img') {
            $node->setAttribute('loading', 'lazy');
            $node->setAttribute('decoding', 'async');
        }
    }
    $body = $doc->getElementsByTagName('body')->item(0);
    $result = '';
    foreach ($body->childNodes as $node) $result .= $doc->saveHTML($node);
    return $result;
}

require_once __DIR__.'/seo.php';

function externalLinkTargets(string $html): string {
    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    foreach ($doc->getElementsByTagName('form') as $form) $form->setAttribute('action', seoUrl($form->getAttribute('action')));
    $currentHost = strtolower($_SERVER['HTTP_HOST'] ?? '');
    foreach ($doc->getElementsByTagName('a') as $link) {
        $href = seoUrl(trim($link->getAttribute('href')));
        $link->setAttribute('href', $href);
        $documentContext = false;
        for ($parent = $link->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode) {
            if (in_array($parent->tagName, ['nav', 'header', 'footer'], true)) { $documentContext = false; break; }
            $classes = preg_split('/\s+/', $parent->getAttribute('class'));
            if (array_intersect($classes, ['legacy-content', 'document-group', 'project-resources'])) $documentContext = true;
        }
        if ($documentContext && preg_match('~\.pdf$~i', rawurldecode(parse_url($href, PHP_URL_PATH) ?: ''))) {
            foreach (iterator_to_array($link->getElementsByTagName('img')) as $image) {
                if (preg_match('~(?:^|/)pdf\.png$~i', parse_url($image->getAttribute('src'), PHP_URL_PATH) ?: '')) $image->parentNode->removeChild($image);
            }
            $link->setAttribute('class', trim($link->getAttribute('class') . ' pdf-link'));
            $pdfIcon = $doc->createElement('i');
            $pdfIcon->setAttribute('class', 'ti ti-file-type-pdf pdf-icon');
            $pdfIcon->setAttribute('aria-hidden', 'true');
            // Replace a generic leading document icon rather than showing two icons.
            $existing = $link->getElementsByTagName('i')->item(0);
            if ($existing && str_contains($existing->getAttribute('class'), 'ti-')) $existing->parentNode->removeChild($existing);
            if ($link->getElementsByTagName('img')->length) {
                $link->setAttribute('class', $link->getAttribute('class') . ' pdf-cover-link');
            } else {
                $label = $doc->createElement('span');
                $label->setAttribute('class', 'pdf-label');
                while ($link->firstChild) $label->appendChild($link->firstChild);
                $link->appendChild($label);
            }
            $link->insertBefore($pdfIcon, $link->firstChild);
        }
        if (!preg_match('~^(?:https?:)?//~i', $href)) continue;
        $parts = parse_url($href);
        if (!$parts || empty($parts['host'])) continue;
        $host = strtolower($parts['host']) . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if ($host === $currentHost) continue;
        $link->setAttribute('target', '_blank');
        $rel = preg_split('/\s+/', trim($link->getAttribute('rel')), -1, PREG_SPLIT_NO_EMPTY);
        $rel = array_filter($rel, fn($value) => strtolower($value) !== 'opener');
        $link->setAttribute('rel', implode(' ', array_unique(array_merge($rel, ['noopener', 'noreferrer']))));
    }
    foreach (['ul', 'ol'] as $tag) foreach ($doc->getElementsByTagName($tag) as $list) {
        $items = 0; $documents = 0;
        foreach ($list->childNodes as $item) {
            if (!$item instanceof DOMElement || $item->tagName !== 'li') continue;
            $items++;
            foreach ($item->getElementsByTagName('a') as $anchor) {
                if (in_array('pdf-link', explode(' ', $anchor->getAttribute('class')), true)) { $documents++; break; }
            }
        }
        if ($items && $items === $documents) $list->setAttribute('class', trim($list->getAttribute('class') . ' pdf-document-list'));
    }
    // Remove the encoding hint, keeping the original HTML doctype.
    foreach (iterator_to_array($doc->childNodes) as $node) {
        if ($node->nodeType === XML_PI_NODE) $doc->removeChild($node);
    }
    return $doc->saveHTML();
}

function layout(string $title, string $description, string $canonical, string $content, int $status = 200): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $origin = rtrim(getenv('CEPS_ORIGIN') ?: 'https://www.ceps.edu.ba', '/');
    $robots = getenv('CEPS_ENV') === 'production' && $status === 200 && strtolower(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '') !== '/pretraga' ? 'index,follow' : 'noindex,nofollow';
    header('X-Robots-Tag: ' . $robots);
    header("Content-Security-Policy: default-src 'self'; script-src 'self' https://www.cognitoforms.com; style-src 'self' https://fonts.googleapis.com https://cdn.jsdelivr.net; style-src-attr 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' https:; media-src 'self' https://ceps.edu.ba https://dl.dropboxusercontent.com; object-src 'none'; frame-src https://www.youtube-nocookie.com https://www.google.com https://www.ceps.edu.ba https://www.cognitoforms.com; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    $pageTitle = esc($title === SCHOOL_NAME ? SCHOOL_NAME : $title . ' | ' . SCHOOL_NAME);
    $canonical = seoUrl($canonical ?: ($_SERVER['REQUEST_URI'] ?? '/'));
    $canonicalTag = $status === 200 ? '<link rel="canonical" href="' . esc($origin . $canonical) . '">' : '';
    require_once __DIR__ . '/shell.php';
    $html = '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>' . $pageTitle . '</title><meta name="description" content="' . esc($description) . '"><meta name="robots" content="' . $robots . '">' . $canonicalTag . '<meta property="og:title" content="' . $pageTitle . '"><meta property="og:description" content="' . esc($description) . '"><link rel="icon" href="https://ceps.edu.ba/App_Themes/Standard/favicon.png?v=ceps" type="image/png"><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&amp;display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.48.0/dist/tabler-icons.min.css"><script src="/assets/news-images.js"></script><link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="/assets/home.css"><link rel="stylesheet" href="/assets/design-system.css"></head><body><a class="skip" href="#content">Preskoči na sadržaj</a>' . schoolHeader() . '<main id="content">' . $content . '</main>' . schoolFooter() . contactWidget() . '<script src="/assets/news-share.js" defer></script><script src="/assets/contact.js" defer></script><script src="/assets/virtual-tour.js" defer></script><script src="/assets/slider.js" defer></script><script src="/assets/announcement.js" defer></script><script src="/assets/navigation.js" defer></script><script src="/assets/mobile-home.js" defer></script><script src="/assets/cooperation.js" defer></script><script src="/assets/study-tabs.js" defer></script><script src="/assets/gallery.js" defer></script><script src="/assets/project-news.js" defer></script></body></html>';
    echo externalLinkTargets($html);
    exit;
}
