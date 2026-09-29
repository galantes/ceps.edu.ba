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
    $safe=articleHtml((string)($row['Tekst']??''));
    return '<div class="visual-editor" hidden><div class="editor-toolbar" role="toolbar" aria-label="Oblikovanje teksta"><button type="button" data-command="bold"><b>B</b></button><button type="button" data-command="italic"><i>I</i></button><button type="button" data-command="formatBlock" data-value="h2">Naslov</button><button type="button" data-command="formatBlock" data-value="p">Odlomak</button><button type="button" data-command="insertUnorderedList">Lista</button><button type="button" data-editor-link>Poveznica</button><button type="button" data-command="undo">Poništi</button><button type="button" data-command="redo">Ponovi</button><button type="button" data-source-toggle>HTML</button></div><div class="editor-canvas" contenteditable="true" role="textbox" aria-label="Sadržaj novosti" aria-multiline="true">'.$safe.'</div><div class="editor-attachments"><label>Dodaj slike ili PDF<input type="file" data-editor-upload multiple accept="image/jpeg,image/png,image/webp,application/pdf"></label><button type="button" data-library>Odaberi iz biblioteke</button><div class="editor-library" hidden></div><p data-upload-status role="status"></p></div></div>';
}
