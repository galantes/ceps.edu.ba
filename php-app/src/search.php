<?php
declare(strict_types=1);
require_once __DIR__ . '/pages.php';

function searchPage(): string {
    $query = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
    $query = preg_replace('/\s+/u', ' ', $query) ?? '';
    if (strlen($query) > 200) $query = '';
    $page = max(1, min(100000, (int)(is_scalar($_GET['page'] ?? 1) ? ($_GET['page'] ?? 1) : 1)));
    $matches = []; $rows = []; $count = 0;
    if ($query !== '') {
        $pages = contentPages();
        $about = json_decode(file_get_contents(dirname(__DIR__) . '/content/about.json'), true);
        $pages['onama'] = ['title'=>'O nama','slug'=>'Onama','html'=>implode(' ', $about['paragraphs'])];
        foreach ($pages as $item) {
            if (preg_match('/' . preg_quote($query, '/') . '/iu', $item['title'] . ' ' . titleText($item['html']))) $matches[] = $item;
        }
        $pattern = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $query) . '%';
        $where = "Hr=1 AND (Naslov LIKE ? ESCAPE '!' OR Tekst LIKE ? ESCAPE '!')";
        $pdo = database();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM Novosti WHERE ' . $where);
        $stmt->execute([$pattern, $pattern]); $count = (int)$stmt->fetchColumn();
        $stmt = $pdo->prepare('SELECT ID,Naslov,Tekst,Datum FROM Novosti WHERE ' . $where . ' ORDER BY Datum DESC,ID DESC LIMIT 20 OFFSET ?');
        $stmt->bindValue(1, $pattern); $stmt->bindValue(2, $pattern); $stmt->bindValue(3, ($page-1)*20, PDO::PARAM_INT); $stmt->execute(); $rows = $stmt->fetchAll();
    }
    ob_start(); ?>
    <section class="intro search-intro"><p class="eyebrow">PRONAĐITE INFORMACIJE</p><h1>Pretraga CEPS-a</h1>
      <form class="site-search" action="/Pretraga" method="get" role="search"><label for="search-query">Pretražite novosti i podstranice</label><div><input id="search-query" name="q" type="search" value="<?= esc($query) ?>" placeholder="Studiji, upisi, Erasmus…" maxlength="200" required><button type="submit" aria-label="Pretraži"><?= icon('search') ?><span>Pretraži</span></button></div></form>
    </section><section class="search-results institution-width" aria-label="Rezultati pretrage">
    <?php if($query === ''): ?><p>Unesite pojam koji želite pronaći.</p><?php else: ?>
      <p class="search-summary">Za „<?= esc($query) ?>”: <?= count($matches) ?> podstranica i <?= $count ?> objava.</p>
      <?php if($matches && $page === 1): ?><h2>Podstranice</h2><div class="search-list"><?php foreach($matches as $item): ?><article><h3><a href="/<?= esc($item['slug']) ?>"><?= esc($item['title']) ?> <?= icon('arrow-up-right') ?></a></h3><p><?= esc(excerpt($item['html'])) ?></p></article><?php endforeach; ?></div><?php endif; ?>
      <?php if($rows): ?><h2>Novosti i objave</h2><div class="search-list"><?php foreach($rows as $row): ?><article><time><?= esc(newsDate($row['Datum'])) ?></time><h3><a href="/Novost?id=<?= (int)$row['ID'] ?>"><?= esc(titleText($row['Naslov'])) ?></a></h3><p><?= esc(excerpt($row['Tekst'])) ?></p></article><?php endforeach; ?></div><?php endif; ?>
      <?php if(!$matches && !$count): ?><p>Nema rezultata. Pokušajte s kraćim ili drugim pojmom.</p><?php endif; ?>
      <?php if($count > 20): ?><nav class="pagination" aria-label="Stranice rezultata"><?php if($page>1): ?><a href="/Pretraga?q=<?= rawurlencode($query) ?>&amp;page=<?= $page-1 ?>">Prethodna</a><?php endif; ?><span>Stranica <?= $page ?> od <?= (int)ceil($count/20) ?></span><?php if($page*20<$count): ?><a href="/Pretraga?q=<?= rawurlencode($query) ?>&amp;page=<?= $page+1 ?>">Sljedeća</a><?php endif; ?></nav><?php endif; ?>
    <?php endif; ?></section>
    <?php return ob_get_clean();
}
