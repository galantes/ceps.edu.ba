<?php
declare(strict_types=1);
function adminMediaRoot(): string { return getenv('CEPS_MEDIA_DIR') ?: dirname(__DIR__).'/var/media'; }
function serveAdminMedia(string $path): never {
    if(!preg_match('~^/media/([a-f0-9]{32}\.(?:jpg|png|webp|pdf))$~D',$path,$m)){http_response_code(404);exit;}
    $file=adminMediaRoot().'/'.$m[1];
    if(!is_file($file)){http_response_code(404);exit;}
    $types=['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp','pdf'=>'application/pdf'];
    header('Content-Type: '.$types[pathinfo($file,PATHINFO_EXTENSION)]);
    header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: sandbox; default-src 'none'");
    header('Cache-Control: public, max-age=31536000, immutable');header('Content-Length: '.filesize($file));
    if($_SERVER['REQUEST_METHOD']!=='HEAD')readfile($file);exit;
}
function adminUpload(PDO $pdo): array {
    $f=$_FILES['file']??null;
    if(!is_array($f)||!isset($f['error'])||$f['error']!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']))throw new InvalidArgumentException('Odaberite datoteku. Provjerite ograničenje veličine na serveru.');
    if($f['size']>25*1024*1024)throw new InvalidArgumentException('Datoteka može imati najviše 25 MB.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
    if(!isset($types[$mime]))throw new InvalidArgumentException('Dozvoljeni su JPG, PNG, WebP i PDF.');
    if(str_starts_with($mime,'image/')) {
        $size=getimagesize($f['tmp_name']);
        if(!$size||$size[0]*$size[1]>40000000||$f['size']>12*1024*1024)throw new InvalidArgumentException('Slika može imati najviše 12 MB i 40 megapiksela.');
    }
    $root=adminMediaRoot();if(!is_dir($root)&&!mkdir($root,0700,true))throw new RuntimeException('Mapa datoteka nije dostupna.');
    $name=bin2hex(random_bytes(16)).'.'.$types[$mime];
    if(!move_uploaded_file($f['tmp_name'],$root.'/'.$name))throw new RuntimeException('Prijenos datoteke nije uspio.');
    try {
        $pdo->prepare('INSERT INTO AdminMedia (Filename,OriginalName,Mime,Bytes,CreatedAt) VALUES (?,?,?,?,?)')->execute([$name,substr(basename($f['name']),0,240),$mime,$f['size'],date('c')]);
    }catch(Throwable $e){unlink($root.'/'.$name);throw $e;}
    return ['url'=>'/media/'.$name,'name'=>basename($f['name']),'mime'=>$mime];
}
function adminMediaPage(PDO $pdo,bool $post): never {
    $message='';
    if($post)try { $file=adminUpload($pdo);$message='Datoteka je prenesena: '.$file['url']; }catch(Throwable $e){$message=$e instanceof InvalidArgumentException?$e->getMessage():'Prijenos nije uspio. Provjerite postavke servera.';}
    $html='<p role="status">'.esc($message).'</p><form class="upload-form" method="post" action="/admin?module=media" enctype="multipart/form-data">'.adminCsrf().'<label>Slika ili PDF<input type="file" name="file" accept="image/jpeg,image/png,image/webp,application/pdf" required></label><button class="primary">Prenesi datoteku</button></form><p>Prenesene datoteke dostupne su putem linka. Slike do 12 MB, PDF do 25 MB.</p><section class="media-library">';
    foreach($pdo->query('SELECT * FROM AdminMedia ORDER BY ID DESC LIMIT 100') as $file) {
        $url='/media/'.$file['Filename'];
        $html.='<article>'.(str_starts_with($file['Mime'],'image/')?'<img src="'.$url.'" alt="'.esc($file['OriginalName']).'" loading="lazy">':'<strong>PDF</strong>').'<p>'.esc($file['OriginalName']).'</p><input aria-label="Link datoteke" readonly value="'.$url.'"><a href="'.$url.'" target="_blank" rel="noopener">Otvori ↗</a></article>';
    }
    adminPage('Slike i dokumenti',$html.'</section>');
}
