<?php
declare(strict_types=1);
require_once __DIR__ . '/shell.php';

function aboutPage(): string {
    $copy = json_decode(file_get_contents(dirname(__DIR__) . '/content/about.json'), true, 512, JSON_THROW_ON_ERROR);
    ob_start(); ?>
    <div class="institution-page">
      <div class="institution-width breadcrumbs"><a href="/">Naslovnica</a><?= icon('chevron-right') ?><span>O nama</span></div>
      <section class="institution-hero institution-width">
        <div><h1>O nama.<br><span>Znanje za budućnost.</span></h1><p class="institution-lead"><?= esc($copy['paragraphs'][0]) ?></p><a class="pill pill-yellow" href="#o-ustanovi">Upoznajte ustanovu <?= icon('arrow-down') ?></a></div>
        <img src="https://ceps.edu.ba/Files/Images/Onama/001.jpg?1" alt="<?= esc(SCHOOL_NAME) ?>" width="800" height="600">
      </section>
      <div class="institution-layout institution-width">
        <aside class="institution-menu"><p class="home-kicker">O USTANOVI</p><nav aria-label="Povezane stranice"><a href="/Onama" aria-current="page"><?= icon('building-bank') ?> O nama</a><a href="<?= esc(schoolLink('/Misija')) ?>"><?= icon('target') ?> Misija, vizija i ciljevi</a><a href="<?= esc(schoolLink('/Akreditacija')) ?>"><?= icon('certificate') ?> Akreditacija</a><a href="<?= esc(schoolLink('/Akademska_organizacija')) ?>"><?= icon('sitemap') ?> Akademska organizacija</a><a href="<?= esc(schoolLink('/Maticni_podaci')) ?>"><?= icon('file-info') ?> Matični podaci</a><a href="<?= esc(schoolLink('/Kontakt')) ?>"><?= icon('mail') ?> Kontakt</a></nav></aside>
        <div><section class="institution-content" id="o-ustanovi"><p class="home-kicker">NAŠ RAZVOJ</p><h2>Osnivanje i razvoj ustanove</h2><div class="institution-history"><?php foreach (array_slice($copy['paragraphs'], 1) as $paragraph): ?><div><span class="history-marker" aria-hidden="true"></span><p><?= esc($paragraph) ?></p></div><?php endforeach; ?></div></section>
        <section class="institution-videos"><h2>Upoznajte CEPS kroz video</h2><div><?php foreach ($copy['videos'] as $i => $video): ?><a href="<?= esc(str_replace('/embed/', '/watch?v=', $video)) ?>"><?= icon('brand-youtube') ?><span>Predstavljanje CEPS-a<strong>Video <?= $i + 1 ?></strong></span><?= icon('arrow-up-right') ?></a><?php endforeach; ?></div></section>
        <section class="institution-next"><div><h2>Pronađite svoj studij.</h2><p>Upoznajte studijske programe i mogućnosti obrazovanja na CEPS-u.</p></div><a class="pill pill-dark" href="/#studiji">Studijski programi <?= icon('arrow-up-right') ?></a></section></div>
      </div>
    </div>
    <?php return ob_get_clean();
}
