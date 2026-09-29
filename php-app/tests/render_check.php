<?php
declare(strict_types=1);
require dirname(__DIR__) . '/src/bootstrap.php';
$sample = '<p>Čćžšđ <strong>tekst</strong><a href="/Novost?id=1">link</a><img src="/Files/x.jpg" onerror="alert(1)"></p><script>alert(1)</script><a href="javascript:alert(1)">bad</a><iframe src="https://example.com"></iframe>';
$result = articleHtml($sample);
foreach (['Čćžšđ', '<strong>tekst</strong>', 'href="/Novost?id=1"', 'src="https://ceps.edu.ba/Files/x.jpg"'] as $expected) {
    if (!str_contains($result, $expected)) throw new RuntimeException('Missing expected content: ' . $expected);
}
foreach (['<script', 'onerror', 'javascript:', '<iframe'] as $forbidden) {
    if (str_contains($result, $forbidden)) throw new RuntimeException('Unsafe content: ' . $forbidden);
}
echo "Render checks passed\n";
if (localUrl('https://www.ceps.edu.ba/Files/a.pdf?x=1#page=2') !== 'https://ceps.edu.ba/Files/a.pdf?x=1#page=2') throw new RuntimeException('Internal URL rewrite failed');
if (!str_contains($result, 'href="https://example.com"')) throw new RuntimeException('Legacy embed link lost');
