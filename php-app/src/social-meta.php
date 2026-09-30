<?php
declare(strict_types=1);

function socialMeta(string $title, string $description, string $url, string $origin, array $options = []): string {
    $fallback = $origin . '/assets/share-ceps.jpg';
    $image = $options['image'] ?? $fallback;
    if (str_starts_with($image, '/') && !str_starts_with($image, '//')) $image = $origin . $image;
    if (!filter_var($image, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($image, PHP_URL_SCHEME) ?: ''), ['http', 'https'], true)) $image = $fallback;
    $type = ($options['type'] ?? '') === 'article' ? 'article' : 'website';
    $alt = $image === $fallback ? 'Visoka škola CEPS — Centar za poslovne studije Kiseljak' : ($options['image_alt'] ?? $title);
    $properties = ['og:title'=>$title, 'og:description'=>$description, 'og:type'=>$type, 'og:url'=>$url, 'og:site_name'=>SCHOOL_NAME, 'og:locale'=>'bs_BA', 'og:image'=>$image, 'og:image:alt'=>$alt];
    if ($image === $fallback) {
        $properties['og:image:width'] = '968';
        $properties['og:image:height'] = '504';
        $properties['og:image:type'] = 'image/jpeg';
    }
    if ($type === 'article' && !empty($options['published'])) {
        try {
            $properties['article:published_time'] = (new DateTimeImmutable($options['published'], new DateTimeZone('Europe/Sarajevo')))->format(DATE_ATOM);
        } catch (Exception) { /* Omit invalid legacy dates. */ }
    }
    $html = '';
    foreach ($properties as $name=>$value) $html .= '<meta property="' . esc($name) . '" content="' . esc($value) . '">';
    foreach (['twitter:card'=>'summary_large_image', 'twitter:title'=>$title, 'twitter:description'=>$description, 'twitter:image'=>$image, 'twitter:image:alt'=>$alt] as $name=>$value) $html .= '<meta name="' . esc($name) . '" content="' . esc($value) . '">';
    return $html;
}
