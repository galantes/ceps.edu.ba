<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$root=dirname(__DIR__,2);
$out=$root.'/deploy/admin-reset';
$private=$out.'/app/var';
if(!is_dir($private))mkdir($private,0700,true);
$token=bin2hex(random_bytes(20));
$expires=time()+172800;
copy(dirname(__DIR__).'/hosting/reset-admin.php',$out.'/reset-admin.php');
file_put_contents($private.'/admin-reset.json',json_encode(['token_hash'=>hash('sha256',$token),'expires_at'=>$expires,'used'=>false],JSON_PRETTY_PRINT)."\n");
chmod($private.'/admin-reset.json',0600);
$instructions="RESET ADMIN LOZINKE — CEPS\n\n".
"Prenesite reset-admin.php u public_html/ceps.edu.ba/.\n".
"Prenesite app/var/admin-reset.json u public_html/ceps.edu.ba/app/var/.\n".
"Ne prepisujte app/var/admin.json iz lokalnog paketa.\n".
"Otvorite https://ceps.edu.ba/reset-admin.php na novom serveru.\n".
"Ako DNS još vodi na stari web, koristite prethodno podešeni hosts unos.\n\n".
"Jednokratni reset ključ: ".$token."\n".
"Ključ važi 48 sati od pripreme paketa; nakon uspjeha se automatski deaktivira.\n".
"Unesite novu lozinku od najmanje 14 znakova i potvrdite je.\n".
"PHP mora moći pisati u app/var/; alat kreira sessions/ ako nedostaje.\n".
"Nakon uspjeha prijavite se na /admin, pa obrišite reset-admin.php i app/var/admin-reset.json sa servera.\n".
"UPUTE.txt ostaje na računaru — ne uploadati na server.\n";
file_put_contents($out.'/UPUTE.txt',$instructions);
echo 'Reset paket: '.$out.PHP_EOL;
