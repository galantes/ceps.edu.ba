<?php
declare(strict_types=1);
function projectPage(array $page): string {
    $catalog = $page['slug'] === 'Projekti';
    $projects = array_filter(contentPages(), fn($p) => str_starts_with($p['slug'], 'Projekt/'));
    $html = $page['html'];
    $html = preg_replace_callback('~(<img\b[^>]*src="[^"]*/funded_eu_logo\.jpg"[^>]*>)(?:\s|<br\s*/?>)*<div>\s*<b>([^<]*Funded by the European Union[^<]*)</b>\s*</div>~si', function ($match) {
        $logo = str_replace('<img ', '<img alt="Co-funded by the European Union" ', $match[1]);
        return '<aside class="project-funding" aria-label="Financiranje projekta">'.$logo.'<p lang="en">'.$match[2].'</p></aside>';
    }, $html);
    if ($catalog) {
        $html = preg_replace('~<p>\s*(?:<b>\s*)?<a\b[^>]*href="[^"]*Projekt/.*?</p>~si', '', $html);
        $html = preg_replace('~<h2>Projekti</h2>~', '', $html);
    }
    $html = preg_replace('~href="https?://(?:www\.)?ceps\.edu\.ba/Projekti\.aspx"~i', 'href="/Projekti"', $html);
    $rows = [];
    if (preg_match('/^projekt_(?:[1-9]|1[0-2])$/D', $page['projectColumn'] ?? '')) {
        $rows = database()->query('SELECT ID,Naslov,Datum,Hr,En FROM Novosti WHERE (Hr=1 OR En=1) AND '.$page['projectColumn'].'=1 ORDER BY Datum DESC,ID DESC')->fetchAll();
    }
    ob_start(); ?>
    <div class="institution-page project-page">
      <div class="institution-width breadcrumbs"><a href="/">Naslovnica</a><?= icon('chevron-right') ?><?php if(!$catalog): ?><a href="/Projekti">Projekti</a><?= icon('chevron-right') ?><?php endif; ?><span><?= $catalog?'Projekti':'Detalji projekta' ?></span></div>
      <header class="content-page-heading institution-width"><p class="home-kicker">ZNANJE I SURADNJA</p><h1><?= esc($page['title']) ?></h1><span class="heading-rule"></span><?php if(!$catalog): ?><nav class="project-shortcuts" aria-label="Sekcije projekta"><a href="#project-about"><?= icon('info-circle') ?>O projektu</a><?php if($rows): ?><a href="#project-news"><?= icon('news') ?>Objave (<?= count($rows) ?>)</a><?php endif; ?><a href="/Projekti"><?= icon('arrow-left') ?>Svi projekti</a></nav><?php endif; ?></header>
      <div class="institution-width project-layout<?= !$catalog && !empty($page['resources']) ? ' project-with-sidebar' : '' ?>">
        <article class="institution-content legacy-content" id="project-about"><?= $html ?></article>
        <?php if($catalog): ?><section class="project-catalog" aria-label="Projekti CEPS-a"><?php foreach($projects as $project): ?><a class="project-card" href="/<?= esc($project['slug']) ?>"><span class="project-card-icon"><?= icon($project['projectColumn']?'briefcase':'users-group') ?></span><h2><?= esc($project['title']) ?></h2><span class="project-card-more"><?= $project['projectColumn']?'O projektu':'Upoznajte tim' ?> <?= icon('arrow-up-right') ?></span></a><?php endforeach; ?></section>
        <?php else: ?>
          <?php if($page['resources']): ?><aside class="institution-content legacy-content project-resources" id="project-resources" aria-label="Linkovi i dokumenti projekta"><h2>Linkovi i dokumenti</h2><?= $page['resources'] ?></aside><?php endif; ?>
          <?php if($rows): ?><section class="institution-content project-news" id="project-news"><h2>Objave projekta</h2>
            <?php $firstLanguage = true; foreach(['Hr'=>'Objave – Bosna i Hercegovina', 'En'=>'Posts in English'] as $language=>$label):
                $posts = array_values(array_filter($rows, fn($row) => (bool)$row[$language]));
                if (!$posts) continue;
            ?>
            <details class="project-news-language" name="project-news-language" <?= $firstLanguage?'open':'' ?> lang="<?= $language === 'En'?'en':'hr' ?>">
              <summary aria-label="<?= esc($label) ?>"><span><img class="project-language-flag" src="/assets/flag-<?= $language==='En'?'gb':'ba' ?>.svg" alt="<?= $language==='En'?'Ujedinjeno Kraljevstvo':'Bosna i Hercegovina' ?>" width="32" height="20"></span><span class="project-news-count"><?= count($posts) ?></span><?= icon('chevron-down') ?></summary>
              <ul class="project-news-list"><?php foreach($posts as $n=>$row): ?>
                <?php if($n===5): ?></ul><details class="project-news-more"><summary><?= $language==='En'?'Show older posts':'Prikaži ranije objave' ?> (<?= count($posts)-5 ?>) <?= icon('chevron-down') ?></summary><ul class="project-news-list"><?php endif; ?>
                <li><time><?= esc(newsDate($row['Datum'])) ?></time><a href="/Novost?id=<?= (int)$row['ID'] ?>"><?= esc(titleText($row['Naslov'])) ?></a><?= icon('arrow-up-right') ?></li>
              <?php endforeach; ?></ul><?php if(count($posts)>5): ?></details><?php endif; ?>
            </details>
            <?php $firstLanguage=false; endforeach; ?>
          </section><?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php return ob_get_clean();
}
