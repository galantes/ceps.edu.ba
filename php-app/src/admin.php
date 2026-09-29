<?php
declare(strict_types=1);
require_once __DIR__.'/admin-media.php';
require_once __DIR__.'/admin-workflow.php';
require_once __DIR__.'/home-settings.php';

function adminConfig(): ?array {
    $path = getenv('CEPS_ADMIN_CONFIG') ?: dirname(__DIR__).'/var/admin.json';
    if (!is_file($path)) return null;
    $config = json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
    return isset($config['username'],$config['password_hash']) ? $config : null;
}
function adminValue(array $source, string $key, string $default=''): string {
    return isset($source[$key]) && is_string($source[$key]) ? $source[$key] : $default;
}
function adminCsrf(): string { return '<input type="hidden" name="csrf" value="'.esc($_SESSION['admin_csrf']).'">'; }
function adminPage(string $title, string $content, int $status=200): never {
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; connect-src 'self'; img-src 'self' https://ceps.edu.ba https://www.ceps.edu.ba; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    echo '<!doctype html><html lang="hr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.esc($title).' | CEPS administracija</title><link rel="stylesheet" href="/assets/admin.css"><script src="/assets/admin-editor.js" defer></script></head><body><header><a href="/admin">CEPS <span>Administracija</span></a><a href="/">Otvori web</a></header><main>';
    if (!empty($_SESSION['admin_user'])) echo '<nav><a href="/admin">Novosti</a><a href="/admin?module=agreements">Sporazumi</a><a href="/admin?module=drafts">Nacrti</a><a href="/admin?module=media">Slike i PDF</a><a href="/admin?module=home">Naslovnica</a><form method="post" action="/admin">'.adminCsrf().'<button name="action" value="logout">Odjava</button></form></nav>';
    echo '<h1>'.esc($title).'</h1>'.$content.'</main></body></html>';exit;
}
function adminFingerprint(array $row): string { ksort($row); return hash('sha256',serialize($row)); }
function adminFlags(): array {
    $flags=['Hr'=>'Hrvatski','En'=>'Engleski','De'=>'Njemački','Aktivnosti'=>'Aktivnosti','Konkursi'=>'Natječaji','Naslovnica'=>'Naslovnica','Istaknuto'=>'Istaknuto','PE'=>'Poslovna ekonomija','Energetika'=>'Energetika','Zastita'=>'Zaštita','Sigurnost'=>'Sigurnost','Promet'=>'Promet'];
    foreach (['Obnovljivi izvori energije','Energetski efikasnija BiH','GROWTH','1FUTURE','Model alternativnog napajanja','IMPACT','RES-UNI','SEE-GL','Skill 2 sustain','Core-ed academy','Prodigy','Projekt 12','Projekt 13','Projekt 14','Projekt 15'] as $index=>$label) $flags['projekt_'.($index+1)]=$label;
    return $flags;
}
function adminLoginAllowed(bool $failure=false): bool {
    $file = dirname(getenv('CEPS_ADMIN_CONFIG') ?: dirname(__DIR__).'/var/admin.json').'/login-'.hash('sha256',$_SERVER['REMOTE_ADDR'] ?? 'unknown').'.json';
    $handle=fopen($file,'c+');
    if (!$handle || !flock($handle,LOCK_EX)) throw new RuntimeException('Login limit unavailable');
    $data=json_decode(stream_get_contents($handle),true) ?: ['start'=>time(),'count'=>0];
    if(time()-$data['start']>=900) $data=['start'=>time(),'count'=>0];
    $allowed=$data['count']<5;
    if($failure) $data['count']++;
    rewind($handle);ftruncate($handle,0);fwrite($handle,json_encode($data));flock($handle,LOCK_UN);fclose($handle);
    return $allowed;
}
function adminRun(): never {
    date_default_timezone_set('Europe/Sarajevo');
    if (getenv('CEPS_ENV')==='production' && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off')) adminPage('Potrebna je HTTPS veza','<p>Administraciju otvorite preko sigurne HTTPS adrese.</p>',403);
    session_name('ceps_admin');
    session_start(['use_strict_mode'=>true,'cookie_httponly'=>true,'cookie_samesite'=>'Strict','cookie_secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off','cookie_path'=>'/admin']);
    $_SESSION['admin_csrf'] ??= bin2hex(random_bytes(32));
    $config=adminConfig();
    if (!$config) { unset($_SESSION['admin_user']); adminPage('Administracija je postavljena','<section><h2>Postavite administratorski račun</h2><p>Prijava još nije aktivirana. Račun se izrađuje na serveru naredbom <code>php bin/create-admin.php</code> iz direktorija php-app.</p><p>Novosti i sporazumi bit će dostupni nakon postavljanja računa.</p></section>',503); }
    if (isset($_SESSION['admin_user']) && (time()-($_SESSION['admin_seen']??0)>1800 || ($_SESSION['admin_version']??'')!==hash('sha256',$config['password_hash']))) unset($_SESSION['admin_user']);
    $post=$_SERVER['REQUEST_METHOD']==='POST';
    if ($post && !hash_equals($_SESSION['admin_csrf'],adminValue($_POST,'csrf'))) adminPage('Zahtjev je istekao','<p>Osvježite stranicu i pokušajte ponovo.</p>',403);
    if ($post && adminValue($_POST,'action')==='logout') { $_SESSION=[];session_regenerate_id(true);header('Location: /admin',true,303);exit; }
    $error='';
    if (empty($_SESSION['admin_user'])) {
        if($post) {
            if(!adminLoginAllowed()) $error='Previše pokušaja. Pokušajte ponovno za 15 minuta.';
            elseif(password_verify(adminValue($_POST,'password'),$config['password_hash']) && hash_equals($config['username'],adminValue($_POST,'username'))) {
                session_regenerate_id(true);$_SESSION['admin_user']=$config['username'];$_SESSION['admin_seen']=time();$_SESSION['admin_version']=hash('sha256',$config['password_hash']);$_SESSION['admin_csrf']=bin2hex(random_bytes(32));header('Location: /admin',true,303);exit;
            } else { adminLoginAllowed(true);$error='Neispravno korisničko ime ili lozinka.'; }
        }
        adminPage('Prijava','<form class="login" method="post" action="/admin">'.adminCsrf().'<p role="alert">'.esc($error).'</p><label>Korisničko ime<input name="username" autocomplete="username" required></label><label>Lozinka<input type="password" name="password" autocomplete="current-password" required></label><button class="primary">Prijavi se</button></form>');
    }
    $_SESSION['admin_seen']=time();
    $pdo=database();
    if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')$pdo->exec('PRAGMA query_only = OFF');
    if(adminValue($_GET,'module')==='home')adminHomeSettings($pdo,$post);
    if(adminValue($_GET,'module')==='media')adminMediaPage($pdo,$post);
    if(adminValue($_GET,'module')==='media-list'){header('Content-Type: application/json');header('Cache-Control: no-store');echo json_encode($pdo->query('SELECT Filename,OriginalName,Mime FROM AdminMedia ORDER BY ID DESC LIMIT 100')->fetchAll());exit;}
    if(adminValue($_GET,'module')==='drafts')adminDraftList($pdo);
    if(adminValue($_GET,'module')==='upload' && $post) {
        header('Content-Type: application/json');header('Cache-Control: no-store');
        try { echo json_encode(adminUpload($pdo)); } catch(Throwable $e) {http_response_code(400);echo json_encode(['error'=>$e instanceof InvalidArgumentException?$e->getMessage():'Prijenos nije uspio.']);} exit;
    }
    $module=adminValue($_GET,'module')==='agreements'?'agreements':'news';
    $table=$module==='news'?'Novosti':'Sporazumi';$name=$module==='news'?'Naslov':'Naziv';
    $base='/admin?module='.$module;
    $edit=isset($_GET['id']);$id=adminValue($_GET,'id');
    if($edit && !preg_match('/^(?:new|[1-9][0-9]{0,9})$/D',$id)) adminPage('Zapis nije pronađen','',404);
    if($edit) {
        $row=[];
        if($id!=='new') { $stmt=$pdo->prepare("SELECT * FROM $table WHERE ID=?");$stmt->execute([$id]);$row=$stmt->fetch();if(!$row) adminPage('Zapis nije pronađen','',404); }
        $version=adminFingerprint($row);
        $values=$row;
        $draft=adminLoadDraft($pdo,$module,$id);
        if($draft){$values=json_decode($draft['Payload'],true,512,JSON_THROW_ON_ERROR);$version=$draft['BaseVersion'];$values['_draft_version']=hash('sha256',$draft['Payload']);}
        if(!$post && isset($_GET['revision'])) {
            $rev=$pdo->prepare('SELECT Payload FROM AdminRevisions WHERE ID=? AND Module=? AND RecordID=?');$rev->execute([adminValue($_GET,'revision'),$module,$id]);
            $payload=$rev->fetchColumn();if(!$payload)adminPage('Verzija nije pronađena','',404);
            $values=json_decode($payload,true,512,JSON_THROW_ON_ERROR);
            $error='Učitana je prethodna verzija. Pregledajte sadržaj i spremite ga kao nacrt ili objavite.';
        }
        if($post) {
            $values=$_POST;
            $version=adminValue($_POST,'version');
            try {
                $data=adminValidate($module,$_POST);
                $action=adminValue($_POST,'action','publish');
                if($action==='preview') adminPage('Pregled prije objave',adminPreview($module,$data).adminEditor($module,$values,$version,$base,$id,''));
                if($action==='draft') {
                    $draftId=adminSaveDraft($pdo,$module,$id,$data,$version,$draft);
                    header('Location: '.$base.'&id='.$id.'&draft='.$draftId.'&saved=1',true,303);exit;
                }
                if($action!=='publish')throw new InvalidArgumentException('Nepoznata radnja.');
                if($pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite') $pdo->exec('PRAGMA query_only = OFF');
                $pdo->beginTransaction();
                if($draft){
                    $draftLock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
                    $check=$pdo->prepare('SELECT Payload FROM AdminDrafts WHERE ID=?'.$draftLock);$check->execute([$draft['ID']]);$latestDraft=$check->fetchColumn();
                    if(!$latestDraft || !hash_equals(hash('sha256',$latestDraft),adminValue($_POST,'_draft_version')))throw new InvalidArgumentException('Nacrt je već objavljen ili izmijenjen. Ponovno otvorite popis nacrta.');
                }
                if($id!=='new') {
                    $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
                    $stmt=$pdo->prepare("SELECT * FROM $table WHERE ID=?".$lock);$stmt->execute([$id]);$latest=$stmt->fetch();
                    if(!$latest || !hash_equals(adminFingerprint($latest),adminValue($_POST,'version'))) throw new RuntimeException('Zapis je u međuvremenu promijenjen. Otvorite ga ponovno prije spremanja.');
                    adminRevision($pdo,$module,$id,$latest);
                    $sql='UPDATE '.$table.' SET '.implode(',',array_map(fn($key)=>"$key=?",array_keys($data))).' WHERE ID=?';
                    $pdo->prepare($sql)->execute([...array_values($data),$id]);
                } else {
                    if($module==='agreements')$data['CreatedAt']=date('Y-m-d H:i:s');
                    $pdo->prepare('INSERT INTO '.$table.' ('.implode(',',array_keys($data)).') VALUES ('.implode(',',array_fill(0,count($data),'?')).')')->execute(array_values($data));
                    $id=(string)$pdo->lastInsertId();
                }
                if($draft)$pdo->prepare('DELETE FROM AdminDrafts WHERE ID=?')->execute([$draft['ID']]);
                $pdo->commit();header('Location: '.$base.'&id='.$id.'&saved=1',true,303);exit;
            } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();$error=$e instanceof InvalidArgumentException || $e instanceof RuntimeException && !$e instanceof PDOException ? $e->getMessage() : 'Spremanje nije uspjelo. Pokušajte ponovo.';error_log('Admin save: '.$e->getMessage()); }
        }
        adminPage($id==='new'?'Novi zapis':'Uredi zapis',adminEditor($module,$values,$version,$base,$id,$error).adminRevisionList($pdo,$module,$id));
    }
    $q=trim(adminValue($_GET,'q'));$page=max(1,min(100000,(int)adminValue($_GET,'page','1')));$offset=($page-1)*30;
    $stmt=$pdo->prepare("SELECT * FROM $table WHERE $name LIKE ? ORDER BY ID DESC LIMIT 30 OFFSET $offset");$stmt->execute(['%'.$q.'%']);
    $html='<div class="tools"><form method="get" action="/admin"><input type="hidden" name="module" value="'.$module.'"><label>Pretraži<input name="q" value="'.esc($q).'"></label><button>Pretraži</button></form><a class="primary" href="'.$base.'&id=new">Dodaj '.($module==='news'?'novost':'sporazum').'</a></div><section class="records">';
    foreach($stmt as $row) $html.='<a href="'.$base.'&id='.$row['ID'].'"><span><strong>'.esc(titleText($row[$name])).'</strong><small>#'.$row['ID'].' · '.esc($row['Datum']??'').'</small></span><span>Uredi →</span></a>';
    $html.='</section><div class="pagination">';if($page>1)$html.='<a href="'.$base.'&q='.rawurlencode($q).'&page='.($page-1).'">Prethodna</a>';
    $count=$pdo->prepare("SELECT COUNT(*) FROM $table WHERE $name LIKE ?");$count->execute(['%'.$q.'%']);
    $html.='<span>Stranica '.$page.'</span>';if($offset+30<(int)$count->fetchColumn())$html.='<a href="'.$base.'&q='.rawurlencode($q).'&page='.($page+1).'">Sljedeća</a>';
    adminPage($module==='news'?'Novosti':'Sporazumi',$html.'</div>');
}
function adminValidate(string $module,array $input): array {
    $key=$module==='news'?'Naslov':'Naziv';$title=trim(adminValue($input,$key));
    if($title==='' || strlen($title)>500) throw new InvalidArgumentException('Unesite naslov do 500 znakova.');
    $rawDate=adminValue($input,'Datum');$date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$rawDate);
    if(!$date || $date->format('Y-m-d\TH:i')!==$rawDate) throw new InvalidArgumentException('Unesite ispravan datum i vrijeme.');
    $data=[$key=>$title,'Datum'=>$date->format('Y-m-d H:i:s')];
    if($module==='news') {
        $text=adminValue($input,'Tekst');if(trim($text)==='' || strlen($text)>2000000) throw new InvalidArgumentException('Unesite sadržaj (najviše 2 MB).');
        $data['Tekst']=$text;
        foreach(adminFlags() as $flag=>$label)$data[$flag]=isset($input[$flag])?1:0;
    } else {
        $path=trim(adminValue($input,'Putanja'));
        $parts=parse_url($path);
        if(!$parts || !preg_match('~\.pdf$~i',$parts['path']??'') || !(((str_starts_with($path,'/Files/') || preg_match('~^/media/[a-f0-9]{32}\.pdf$~D',$path)) && !str_starts_with($path,'//')) || (($parts['scheme']??'')==='https' && in_array(strtolower($parts['host']??''),['ceps.edu.ba','www.ceps.edu.ba'],true)))) throw new InvalidArgumentException('Unesite PDF poveznicu iz CEPS mape Files.');
        if(!in_array(adminValue($input,'Kategorija'),['privreda','skolstvo'],true)) throw new InvalidArgumentException('Odaberite kategoriju.');
        $sort=filter_var(adminValue($input,'Sort','0'),FILTER_VALIDATE_INT);if($sort===false || abs($sort)>1000000)throw new InvalidArgumentException('Neispravan redoslijed.');
        $data+=['Putanja'=>$path,'Kategorija'=>$input['Kategorija'],'Sort'=>$sort,'Aktivan'=>isset($input['Aktivan'])?1:0];
    }
    return $data;
}
function adminEditor(string $module,array $row,string $version,string $base,string $id,string $error): string {
    $key=$module==='news'?'Naslov':'Naziv';
    $html='<a href="'.$base.'">← Natrag na popis</a>';
    if(isset($_GET['saved']) && !$error)$html.='<p class="success" role="status">Promjene su spremljene.</p>';
    if($error)$html.='<p class="error" role="alert">'.esc($error).'</p>';
    $html.='<form class="editor" method="post" action="'.$base.'&id='.esc($id).(isset($_GET['draft'])?'&draft='.esc(adminValue($_GET,'draft')):'').'">'.adminCsrf().'<input type="hidden" name="version" value="'.esc($version).'"><input type="hidden" name="_draft_version" value="'.esc((string)($row['_draft_version']??'')).'"><label>Naslov<input name="'.$key.'" value="'.esc((string)($row[$key]??'')).'" required maxlength="500"></label><label>Datum i vrijeme<input name="Datum" type="datetime-local" required value="'.esc(substr(str_replace(' ','T',(string)($row['Datum']??date('Y-m-d H:i:s'))),0,16)).'"></label>';
    if($module==='news') {
        $html.='<label>Sadržaj<textarea name="Tekst" id="news-html" rows="22" required>'.esc((string)($row['Tekst']??'')).'</textarea></label>'.adminEditorTools($row).'<p class="hint">Koristite vizualni editor ili HTML prikaz. Spremite nacrt dok pripremate objavu.</p><fieldset><legend>Jezici i rubrike</legend><div class="flags">';
        foreach(adminFlags() as $flag=>$label) $html.='<label><input type="checkbox" name="'.$flag.'" value="1"'.(!empty($row[$flag]) || $id==='new' && !$row && $flag==='Hr'?' checked':'').'>'.esc($label).'</label>';
        $html.='</div></fieldset><p>Nacrt nije javno dostupan. Objavi promjene ažurira web. SEO adresa nastaje automatski.</p>';
        if($id!=='new') $html.='<a target="_blank" rel="noopener" href="'.esc(seoUrl('/Novost?id='.$id)).'">Otvori javnu novost ↗</a>';
    } else {
        $html.='<div class="agreement-upload"><label>Prenesi PDF<input type="file" data-agreement-upload accept="application/pdf"></label><p data-upload-status role="status"></p><a href="/admin?module=media" target="_blank">Biblioteka dokumenata ↗</a></div><label>PDF poveznica<input name="Putanja" value="'.esc((string)($row['Putanja']??'')).'" placeholder="/Files/2026/sporazum.pdf" required></label><label>Kategorija<select name="Kategorija">';
        foreach(['privreda'=>'Partneri iz privrede','skolstvo'=>'Visokoškolski partneri'] as $value=>$label)$html.='<option value="'.$value.'"'.(($row['Kategorija']??'')===$value?' selected':'').'>'.$label.'</option>';
        $html.='</select></label><label>Redoslijed<input type="number" name="Sort" value="'.esc((string)($row['Sort']??0)).'"></label><label class="check"><input type="checkbox" name="Aktivan" value="1"'.(!empty($row['Aktivan'])?' checked':'').'>Aktivan sporazum</label><p class="hint">Odaberite postojeći PDF u biblioteci ili prenesite novi dokument.</p>';
    }
    return $html.'<div class="save"><button type="submit" name="action" value="draft">Spremi nacrt</button><button type="submit" name="action" value="preview">Pregled</button><button class="primary" type="submit" name="action" value="publish">Objavi promjene</button><a href="'.$base.'">Odustani</a></div></form>';
}
