<?php
declare(strict_types=1);

const NEWS_DEFAULT_IMAGE = 'https://ceps.edu.ba/Files/Images/zgrada1.jpg';

function newsImage(?string $html): string {
    if (!$html || stripos($html, '<img') === false) return NEWS_DEFAULT_IMAGE;
    $doc = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $image = $doc->getElementsByTagName('img')->item(0);
    $src = trim($image?->getAttribute('src') ?? '');
    if ($src === '') return NEWS_DEFAULT_IMAGE;
    if (preg_match('~^/media/[a-f0-9]{32}\.(?:jpg|png|webp)$~D', $src)) return $src;
    if (str_starts_with($src, '//')) $src = 'https:' . $src;
    $scheme = parse_url($src, PHP_URL_SCHEME);
    if ($scheme !== null && !in_array(strtolower((string)$scheme), ['https', 'http'], true)) return NEWS_DEFAULT_IMAGE;
    if ($scheme === null) {
        $src = preg_replace('#^(?:(?:\.{1,2}|~)/)+#', '', $src);
        return 'https://ceps.edu.ba/' . ltrim($src, '/');
    }
    $host = strtolower(parse_url($src, PHP_URL_HOST) ?: '');
    if (in_array($host, ['ceps.edu.ba', 'www.ceps.edu.ba'], true)) {
        $src = preg_replace('#^https?://(?:www\.)?ceps\.edu\.ba#i', 'https://ceps.edu.ba', $src);
    }
    return $src;
}
