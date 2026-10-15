<?php
declare(strict_types=1);
function homeDefaults(): array {
    $copy=json_decode(file_get_contents(dirname(__DIR__).'/content/home.json'),true,512,JSON_THROW_ON_ERROR);$programmes=[];foreach($copy['programmes'] as $p)$programmes[$p['url']]=$p;
    $images=json_decode(file_get_contents(dirname(__DIR__).'/content/hero-images.json'),true,512,JSON_THROW_ON_ERROR);
    $slides=[];foreach($images as $url=>$image){$p=$programmes[$url];$slides[]=['title'=>$p['title'],'description'=>implode(' ',$p['paragraphs']),'url'=>'/'.$url,'image'=>$image,'enabled'=>true,'order'=>count($slides)+1];}
    return ['popup'=>['enabled'=>true,'url'=>'/Novost?id=1473','image'=>'https://ceps.edu.ba/Files/2026/popupisi.jpg','alt'=>'Upisi na CEPS — otvorite obavijest'],'slides'=>$slides];
}
function homeSettings(?PDO $pdo=null): array {
    $defaults=homeDefaults();
    try{$pdo??=database();$row=$pdo->query("SELECT Payload FROM AdminSettings WHERE SettingKey='home'")->fetchColumn();return $row?json_decode($row,true,512,JSON_THROW_ON_ERROR):$defaults;}
    catch(PDOException $e){if(in_array((string)$e->getCode(),['42S02','HY000'],true))return $defaults;throw $e;}
}
function adminHomeSettings(PDO $pdo,bool $post): never {
    $data=homeSettings($pdo);$version=hash('sha256',json_encode($data));$error='';
    if(!$post && isset($_GET['revision'])) {
        $stmt=$pdo->prepare("SELECT Payload FROM AdminRevisions WHERE ID=? AND Module='home' AND RecordID=1");$stmt->execute([adminValue($_GET,'revision')]);$old=$stmt->fetchColumn();
        if(!$old)adminPage('Verzija nije pronađena','',404);
        $data=json_decode($old,true,512,JSON_THROW_ON_ERROR);$error='Učitana je prethodna verzija. Objavite postavke za povrat.';
    }
    if($post)try{
        if(!hash_equals($version,adminValue($_POST,'version')))throw new InvalidArgumentException('Postavke su promijenjene. Osvježite stranicu.');
        $next=['popup'=>['enabled'=>isset($_POST['popup_enabled']),'url'=>adminValue($_POST,'popup_url'),'image'=>adminValue($_POST,'popup_image'),'alt'=>adminValue($_POST,'popup_alt')],'slides'=>[]];
        foreach(['url','image'] as $key)adminSettingUrl($next['popup'][$key]);
        for($i=0;$i<count($data['slides']);$i++){
            $item=['enabled'=>isset($_POST['enabled_'.$i]),'order'=>(int)adminValue($_POST,'order_'.$i,(string)$i)];
            foreach(['title','description','url','image'] as $key)$item[$key]=trim(adminValue($_POST,$key.'_'.$i));
            if($item['title']===''||strlen($item['title'])>500)throw new InvalidArgumentException('Svaki slajd mora imati naslov do 500 znakova.');
            foreach(['url','image'] as $key)if($item[$key]!=='')adminSettingUrl($item[$key]);
            $next['slides'][]=$item;
        }
        if(!array_filter($next['slides'],fn($s)=>$s['enabled']))throw new InvalidArgumentException('Ostavite barem jedan aktivni slajd.');
        $pdo->beginTransaction();
        // First install creates this row; compare and update atomically to reject stale forms.
        $lock=$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $current=$pdo->query("SELECT Payload FROM AdminSettings WHERE SettingKey='home'".$lock)->fetchColumn();
        if(!hash_equals($version,hash('sha256',json_encode(json_decode($current,true,512,JSON_THROW_ON_ERROR)))))throw new InvalidArgumentException('Postavke su promijenjene. Osvježite stranicu.');
        adminRevision($pdo,'home','1',$data);
        $pdo->prepare("UPDATE AdminSettings SET Payload=? WHERE SettingKey='home'")->execute([json_encode($next,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
        $pdo->commit();header('Location: /admin?module=home&saved=1',true,303);exit;
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=$e instanceof InvalidArgumentException?$e->getMessage():'Spremanje nije uspjelo.';}
    $html='<p>Uredite naslovnicu. Slike birajte iz biblioteke i kopirajte njihov link. Popup se automatski zatvara nakon 15 sekundi.</p><a href="/admin?module=media" target="_blank">Otvori biblioteku ↗</a><p role="status">'.esc($error?: (isset($_GET['saved'])?'Postavke su spremljene.':'')).'</p><form class="editor" method="post" action="/admin?module=home">'.adminCsrf().'<input type="hidden" name="version" value="'.$version.'"><h2>Popup</h2><label class="check"><input type="checkbox" name="popup_enabled"'.($data['popup']['enabled']?' checked':'').'>Prikazuj popup</label>';
    foreach(['image'=>'Slika','url'=>'Link na novost','alt'=>'Opis slike'] as $key=>$label)$html.='<label>'.$label.'<input name="popup_'.$key.'" value="'.esc($data['popup'][$key]).'" required></label>';
    $html.='<h2>Slajder</h2>';
    foreach($data['slides'] as $i=>$slide){$html.='<details class="slide-settings"><summary>'.esc($slide['title']).'</summary><label class="check"><input type="checkbox" name="enabled_'.$i.'"'.($slide['enabled']?' checked':'').'>Prikaži slajd</label><label>Redoslijed<input type="number" name="order_'.$i.'" value="'.$slide['order'].'"></label>';
        foreach(['title'=>'Naslov','description'=>'Opis','url'=>'Link','image'=>'Slika'] as $key=>$label)$html.='<label>'.$label.'<input name="'.$key.'_'.$i.'" value="'.esc($slide[$key]).'"></label>';
        $html.='</details>';
    }
    adminPage('Naslovnica — slajder i popup',$html.'<div class="save"><button class="primary">Objavi postavke</button></div></form>'.adminRevisionList($pdo,'home','1'));
}
function adminSettingUrl(string $url): void {
    if(strlen($url)>2000 || preg_match('/[\x00-\x20]/',$url))throw new InvalidArgumentException('Neispravan link.');
    if(str_starts_with($url,'/')&&!str_starts_with($url,'//')&&!str_contains($url,'\\'))return;
    $p=parse_url($url);if(!$p||($p['scheme']??'')!=='https'||!in_array(strtolower($p['host']??''),['ceps.edu.ba','www.ceps.edu.ba','dl.dropboxusercontent.com'],true))throw new InvalidArgumentException('Koristite lokalni link ili HTTPS link CEPS-a.');
}
