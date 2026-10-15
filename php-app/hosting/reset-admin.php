<?php
/** Temporary FTP password recovery. Requires a private, single-use reset key. */
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
$secure=!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off';
$local=PHP_SAPI==='cli-server' && in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
if(!$secure && !$local){http_response_code(403);exit('Reset otvorite preko HTTPS veze.');}
$private=__DIR__.'/app/var';
$keyFile=$private.'/admin-reset.json';
if(!is_file($keyFile)){http_response_code(404);exit('Reset nije omogućen. Prenesite i privatni admin-reset.json iz paketa.');}
$state=json_decode(file_get_contents($keyFile),true);
if(!is_array($state)||!isset($state['token_hash'],$state['expires_at'])||!empty($state['used'])||(int)$state['expires_at']<time()){
    http_response_code(404);exit('Reset ključ je iskorišten ili istekao. Pripremite novi reset paket.');
}
$sessionDirectory=$private.'/sessions';
if((!is_dir($sessionDirectory)&&!mkdir($sessionDirectory,0700,true))||!is_writable($sessionDirectory)){
    http_response_code(503);exit('PHP mora moći pisati u app/var/sessions. Provjerite dozvole foldera.');
}
ini_set('session.save_path',$sessionDirectory);
ini_set('session.use_strict_mode','1');
session_name('CEPS_RESET');
session_set_cookie_params(['secure'=>$secure,'httponly'=>true,'samesite'=>'Strict','path'=>'/']);
if(!session_start()){http_response_code(503);exit('Sesiju nije moguće pokrenuti. Provjerite PHP postavke sesije.');}
$_SESSION['reset_csrf']??=bin2hex(random_bytes(32));
function resetInput(string $key): string {return is_string($_POST[$key]??null)?$_POST[$key]:'';}
function resetEsc(string $text): string {return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$accountFile=$private.'/admin.json';
$config=is_file($accountFile)?json_decode(file_get_contents($accountFile),true):['username'=>'bojangalic'];
if(!is_array($config)||!preg_match('/^[a-zA-Z0-9_.-]{3,60}$/D',(string)($config['username']??''))){
    http_response_code(503);exit('Admin konfiguracija nije ispravna. Provjerite app/var/admin.json.');
}
$error='';$success=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($_SESSION['reset_csrf'],resetInput('csrf'))){http_response_code(403);$error='Zahtjev je istekao. Osvježite stranicu.';}
    elseif(strlen(resetInput('password'))<14||strlen(resetInput('password'))>1024){http_response_code(422);$error='Lozinka treba imati između 14 i 1024 bajta.';}
    elseif(!hash_equals(resetInput('password'),resetInput('confirm'))){http_response_code(422);$error='Lozinke nisu jednake.';}
    else{
        $handle=fopen($keyFile,'r+');
        if(!$handle||!flock($handle,LOCK_EX)){http_response_code(503);$error='Reset datoteku nije moguće otvoriti za pisanje.';}
        else{
            try{
                $locked=json_decode(stream_get_contents($handle),true);
                if(!is_array($locked)||!empty($locked['used'])||(int)($locked['expires_at']??0)<time()||!hash_equals((string)($locked['token_hash']??''),hash('sha256',resetInput('key')))){
                    http_response_code(403);$error='Reset ključ nije ispravan ili je već iskorišten.';
                }else{
                    // Consume the recovery key before replacing credentials.
                    // A failed replacement requires a fresh key uploaded through FTP.
                    $config['password_hash']=password_hash(resetInput('password'),PASSWORD_DEFAULT);
                    $content=json_encode($config,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
                    $previousMask=umask(0077);
                    $temporary=$private.'/admin-reset-'.bin2hex(random_bytes(8)).'.tmp';
                    $file=fopen($temporary,'x');umask($previousMask);
                    if(!$file)throw new RuntimeException('Cannot create temporary admin file');
                    try{if(fwrite($file,$content)!==strlen($content))throw new RuntimeException('Cannot write admin file');}
                    finally{fclose($file);}
                    $locked['used']=true;$locked['used_at']=time();
                    $disabled=json_encode($locked,JSON_THROW_ON_ERROR)."\n";
                    rewind($handle);
                    if(!ftruncate($handle,0)||fwrite($handle,$disabled)!==strlen($disabled)||!fflush($handle))throw new RuntimeException('Cannot consume key');
                    if(!rename($temporary,$accountFile))throw new RuntimeException('Cannot replace admin file');
                    @chmod($accountFile,0600);
                    $limitFile=$private.'/login-'.hash('sha256',$_SERVER['REMOTE_ADDR']??'unknown').'.json';
                    if(is_file($limitFile))@unlink($limitFile);
                    $_SESSION=[];session_destroy();$success=true;
                }
            }catch(Throwable $failure){
                error_log('CEPS admin reset: '.$failure->getMessage());
                if(isset($temporary)&&is_file($temporary))@unlink($temporary);
                http_response_code(503);$error='Spremanje nije uspjelo. Provjerite dozvole app/var i prenesite novi reset ključ.';
            }finally{flock($handle,LOCK_UN);fclose($handle);}
        }
        if($handle && $error && is_resource($handle))fclose($handle);
    }
}
?>
<!doctype html><html lang="bs"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset admin lozinke | CEPS</title><style>body{margin:0;padding:32px 16px;background:#f3f5f7;color:#202b33;font:16px/1.6 system-ui,sans-serif}main{max-width:440px;margin:5vh auto;padding:28px;background:#fff;border:1px solid #dde3e8;border-radius:16px}h1{font-size:25px;margin:0 0 16px}label{display:block;margin-top:16px;font-weight:600}input{box-sizing:border-box;width:100%;padding:12px;margin-top:6px;border:1px solid #bac6cf;border-radius:8px;font:inherit}button,a{font:inherit}button{padding:12px 20px;margin-top:24px;border:0;border-radius:8px;background:#ffff00;color:#202b33;font-weight:700;cursor:pointer}.error{color:#9b2424}small{display:block;color:#53616c;margin-top:12px}</style></head><body><main>
<?php if($success): ?><h1>Lozinka je promijenjena</h1><p>Korisničko ime: <strong><?= resetEsc($config['username']) ?></strong></p><p>Prijavite se novom lozinkom na <a href="/admin">/admin</a>.</p><p>Reset ključ je deaktiviran. Obrišite reset-admin.php i app/var/admin-reset.json preko FTP-a.</p>
<?php else: ?><h1>Reset admin lozinke</h1><p>Korisničko ime: <strong><?= resetEsc($config['username']) ?></strong></p><?php if($error): ?><p class="error" role="alert"><?= resetEsc($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= resetEsc($_SESSION['reset_csrf']) ?>"><label>Jednokratni reset ključ<input name="key" type="password" autocomplete="off" required></label><label>Nova lozinka<input name="password" type="password" autocomplete="new-password" minlength="14" maxlength="1024" required></label><label>Ponovi lozinku<input name="confirm" type="password" autocomplete="new-password" minlength="14" maxlength="1024" required></label><small>Najmanje 14 znakova. Novu lozinku unosite ovdje, ne u chat.</small><button type="submit">Promijeni lozinku</button></form><?php endif; ?>
</main></body></html>
