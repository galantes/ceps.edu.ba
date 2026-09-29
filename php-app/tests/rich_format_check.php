<?php
require dirname(__DIR__).'/src/bootstrap.php';
$html=articleHtml('<p style="text-align:right;font-weight:700;color:#123456;position:fixed;background-image:url(javascript:bad)" onclick="bad()">Word <strong>tekst</strong></p><table><tr><td style="text-align:center">5</td></tr></table><script>bad()</script>');
foreach(['text-align:right','font-weight:700','color:#123456','<strong>tekst</strong>','<table>','text-align:center'] as $expected)if(!str_contains($html,$expected))throw new RuntimeException('Missing formatting: '.$expected);
foreach(['onclick','javascript','position:','background-image','<script'] as $bad)if(str_contains($html,$bad))throw new RuntimeException('Unsafe formatting survived: '.$bad);
echo "PASS: formatting survives publication; active HTML and unsafe CSS are stripped.\n";
