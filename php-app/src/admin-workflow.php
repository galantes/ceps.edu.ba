<?php
declare(strict_types=1);
function adminLoadDraft(PDO $pdo,string $module,string $id): ?array {
    if(!isset($_GET['draft']))return null;
    $stmt=$pdo->prepare('SELECT * FROM AdminDrafts WHERE ID=? AND Module=?');$stmt->execute([adminValue($_GET,'draft'),$module]);$row=$stmt->fetch();
    if(!$row || (string)($row['RecordID']??'new')!==$id)adminPage('Nacrt nije pronađen','',404);
    return $row;
}
function adminSaveDraft(PDO $pdo,string $module,string $id,array $data,string $version,?array $draft): int {
    $payload=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if($draft) {
        if(!hash_equals(hash('sha256',$draft['Payload']),adminValue($_POST,'_draft_version')))throw new InvalidArgumentException('Nacrt je promijenjen u drugoj kartici. Ponovno ga otvorite.');
        $stmt=$pdo->prepare('UPDATE AdminDrafts SET Payload=?,UpdatedAt=? WHERE ID=? AND Payload=?');$stmt->execute([$payload,date('c'),$draft['ID'],$draft['Payload']]);
        if($stmt->rowCount()!==1){
            $check=$pdo->prepare('SELECT Payload FROM AdminDrafts WHERE ID=?');$check->execute([$draft['ID']]);
            if($check->fetchColumn()!==$payload)throw new InvalidArgumentException('Nacrt nije spremljen jer je u međuvremenu promijenjen.');
        }
        return (int)$draft['ID'];
    }
    $pdo->prepare('INSERT INTO AdminDrafts (Module,RecordID,Payload,BaseVersion,UpdatedAt) VALUES (?,?,?,?,?)')->execute([$module,$id==='new'?null:(int)$id,$payload,$version,date('c')]);
    return (int)$pdo->lastInsertId();
}
function adminDraftList(PDO $pdo): never {
    $html='<p>Nacrti nisu objavljeni na webu.</p><section class="records">';$count=0;
    foreach($pdo->query('SELECT * FROM AdminDrafts ORDER BY ID DESC') as $row){$data=json_decode($row['Payload'],true);$html.='<a href="/admin?module='.esc($row['Module']).'&id='.($row['RecordID']??'new').'&draft='.$row['ID'].'"><span><strong>'.esc($data['Naslov']??$data['Naziv']??'Nacrt').'</strong><small>'.esc($row['UpdatedAt']).'</small></span><span>Uredi nacrt →</span></a>';$count++;}
    if(!$count)$html.='<p>Nema spremljenih nacrta.</p>';
    adminPage('Nacrti',$html.'</section>');
}
function adminRevision(PDO $pdo,string $module,string $id,array $row): void {
    $pdo->prepare('INSERT INTO AdminRevisions (Module,RecordID,Payload,CreatedAt) VALUES (?,?,?,?)')->execute([$module,$id,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),date('c')]);
}
function adminRevisionList(PDO $pdo,string $module,string $id): string {
    if($id==='new')return '';
    $stmt=$pdo->prepare('SELECT ID,CreatedAt FROM AdminRevisions WHERE Module=? AND RecordID=? ORDER BY ID DESC LIMIT 20');$stmt->execute([$module,$id]);
    $html='<details class="revisions"><summary>Povijest izmjena</summary><p>Učitavanje verzije ne mijenja javni sadržaj dok ne odaberete Objavi promjene.</p>';
    foreach($stmt as $row)$html.='<p><a href="/admin?module='.$module.'&id='.$id.'&revision='.$row['ID'].'">Učitaj verziju od '.esc($row['CreatedAt']).'</a></p>';
    return $html.'</details>';
}
function adminPreview(string $module,array $data): string {
    $html='<section class="admin-preview"><p class="hint">PREGLED — sadržaj još nije spremljen ni objavljen</p><h2>'.esc($data['Naslov']??$data['Naziv']).'</h2><p>'.esc($data['Datum']).'</p>';
    if($module==='news')$html.=articleHtml($data['Tekst']);else $html.='<a href="'.esc($data['Putanja']).'" target="_blank" rel="noopener">Otvori PDF ↗</a>';
    return $html.'</section>';
}
function adminEditorTools(array $row): string {
    return '<div class="editor-attachments"><label>Dodaj slike ili PDF<input type="file" data-editor-upload multiple accept="image/jpeg,image/png,image/webp,application/pdf"></label><button type="button" data-library>Odaberi iz biblioteke</button><div class="editor-library" hidden></div><p data-upload-status role="status" aria-live="polite"></p><p class="hint">Zalijepite tekst iz Worda ili sliku s Ctrl+V. Slike možete i povući u editor.</p></div>';
}

function adminDeletePage(PDO $pdo, bool $post): never {
    $module=adminValue($_GET,'module');
    $id=adminValue($_GET,'delete');
    if(!in_array($module,['news','drafts'],true) || !preg_match('/^[1-9][0-9]{0,9}$/D',$id))adminPage('Zapis nije pronađen','',404);
    $table=$module==='news'?'Novosti':'AdminDrafts';
    $back='/admin?module='.$module;
    if($post)$pdo->beginTransaction();
    try {
        $lock=$post && $pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'?' FOR UPDATE':'';
        $stmt=$pdo->prepare('SELECT * FROM '.$table.' WHERE ID=?'.$lock);$stmt->execute([$id]);$row=$stmt->fetch();
        if(!$row){if($pdo->inTransaction())$pdo->rollBack();adminPage('Zapis nije pronađen','<p>Zapis je možda već izbrisan.</p><a href="'.$back.'">Natrag na popis</a>',404);}
        if($post){
            if(adminValue($_POST,'action')!=='delete' || !hash_equals(adminFingerprint($row),adminValue($_POST,'version'))){
                $pdo->rollBack();adminPage('Potvrda je istekla','<p>Sadržaj je promijenjen. Ponovno otvorite potvrdu brisanja.</p><a href="'.$back.'&delete='.$id.'">Pregledaj zapis</a>',409);
            }
            if($module==='news'){
                adminRevision($pdo,'news',$id,$row);
                $pdo->prepare("DELETE FROM AdminDrafts WHERE Module='news' AND RecordID=?")->execute([$id]);
            }
            $pdo->prepare('DELETE FROM '.$table.' WHERE ID=?')->execute([$id]);
            $pdo->commit();header('Location: '.$back.'&deleted=1',true,303);exit;
        }
        $data=$module==='drafts'?json_decode($row['Payload'],true,512,JSON_THROW_ON_ERROR):$row;
        $title=titleText($data['Naslov']??$data['Naziv']??'Nacrt');
        $html='<section class="delete-confirm"><p class="eyebrow">POTVRDA BRISANJA</p><h2>'.esc($title).'</h2><p>'.($module==='news'?'Novost će biti uklonjena s weba zajedno s pripadajućim nacrtima.':'Izbrisat će se samo ovaj nacrt. Objavljeni sadržaj ostaje nepromijenjen.').'</p><p>Ovu radnju nije moguće poništiti kroz administraciju. Slike i PDF datoteke ostaju u biblioteci.</p><form method="post" action="'.$back.'&delete='.$id.'">'.adminCsrf().'<input type="hidden" name="version" value="'.esc(adminFingerprint($row)).'"><div class="delete-actions"><a class="primary" href="'.$back.'">Odustani</a><button class="danger-button" name="action" value="delete">Da, izbriši '.($module==='news'?'novost':'nacrt').'</button></div></form></section>';
        adminPage($module==='news'?'Izbriši novost':'Izbriši nacrt',$html);
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
