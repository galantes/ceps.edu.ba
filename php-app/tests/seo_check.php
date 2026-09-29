<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$cases = [
    '/StudijIT.aspx?utm_Source=Test#plan' => '/studiji/informacijske-tehnologije?utm_Source=Test#plan',
    'https://www.ceps.edu.ba/Cisco.aspx#content' => '/cisco-akademija#content',
    '/Files/document.pdf#page=2' => '/Files/document.pdf#page=2',
    'https://example.com/Cisco' => 'https://example.com/Cisco',
    'mailto:info@ceps.edu.ba' => 'mailto:info@ceps.edu.ba',
    '?page=2' => '?page=2',
    '#content' => '#content',
    '/Default.aspx' => '/',
];
foreach ($cases as $input=>$expected) {
    if (seoUrl($input) !== $expected) throw new RuntimeException('URL mismatch: '.$input);
}
foreach (seoRoutes() as $old=>$new) {
    if (seoUrl($new) !== $new || seoInternalPath($new) !== $old) throw new RuntimeException('Route mismatch: '.$old);
}
if (seoSlug('Čć Šž Đ / Test') !== 'cc-sz-d-test') throw new RuntimeException('Transliteration failed');
echo "SEO route and preservation checks passed\n";
