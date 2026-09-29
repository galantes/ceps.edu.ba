<?php
declare(strict_types=1);
require_once __DIR__ . '/shell.php';
require_once __DIR__ . '/news-image.php';

function homeAsset(string $path): string {
    return 'https://ceps.edu.ba/' . ltrim(preg_replace('~^https?://(?:www\.)?ceps\.edu\.ba/~i', '', $path), '/');
}

function studyIcon(string $url): string {
    $names = ['pravne-nauke.aspx'=>'scale', 'StudijPromet.aspx'=>'road', 'StudijEnergetika.aspx'=>'bolt', 'StudijSigurnost.aspx'=>'shield-check', 'StudijPE.aspx'=>'chart-bar', 'StudijIT.aspx'=>'device-desktop-code', 'StudijZdravstvo.aspx'=>'medical-cross', 'StudijZastita.aspx'=>'tools', 'StudijEng.aspx'=>'world'];
    return icon($names[$url]);
}

function homePage(): string {
    $copy = json_decode(file_get_contents(dirname(__DIR__) . '/content/home.json'), true, 512, JSON_THROW_ON_ERROR);
    $programmes = [];
    foreach ($copy['programmes'] as $programme) $programmes[$programme['url']] = $programme;
    $studies = [
        ['StudijIT.aspx','Informacijske tehnologije','Files/Images/Informacijske_tehnologije.jpg','01'],
        ['StudijZdravstvo.aspx','Zdravstvene studije','Files/Images/zdravstvo.jpg','02'],
        ['StudijPE.aspx','Poslovna ekonomija','Files/Images/ekonomija_1.jpg','03'],
        ['pravne-nauke.aspx','Pravne nauke',null,'04'],
        ['StudijPromet.aspx','Promet',null,'05'],
        ['StudijEnergetika.aspx','Energetika',null,'06'],
        ['StudijSigurnost.aspx','Studij sigurnosti',null,'07'],
        ['StudijZastita.aspx','Zaštita na radu i zaštita od požara',null,'08'],
        ['StudijEng.aspx','Studij na engleskom jeziku',null,'09'],
    ];
    $studies = array_map(fn($i) => $studies[$i], [3,4,5,6,2,0,1,7,8]);
    $pdo = database();
    $news = $pdo->query('SELECT ID,Naslov,Datum,Tekst,Istaknuto FROM Novosti WHERE Hr=1 AND Naslovnica=1 ORDER BY Datum DESC,ID DESC LIMIT 4')->fetchAll();
    require_once __DIR__.'/home-settings.php';
    $settings=homeSettings($pdo);$popup=$settings['popup'];
    $slides=array_values(array_filter($settings['slides'],fn($s)=>$s['enabled']));
    usort($slides,fn($a,$b)=>$a['order']<=>$b['order']);
    ob_start(); ?>
    <?php if($popup['enabled']): ?><dialog class="home-announcement" aria-label="Obavijest o upisima"><button class="announcement-close" type="button" aria-label="Zatvori obavijest" autofocus><?= icon('x') ?></button><a href="<?= esc($popup['url']) ?>"><img src="<?= esc($popup['image']) ?>" alt="<?= esc($popup['alt']) ?>" fetchpriority="high"></a></dialog><?php endif ?>

    <div class="home">
      <section class="home-hero home-width" aria-label="Upoznajte CEPS" aria-roledescription="vrtuljak" data-slider>
        <?php foreach ($slides as $i => $slide): ?>
        <div class="hero-slide" role="group" aria-roledescription="slajd" aria-label="<?= $i + 1 ?> od <?= count($slides) ?>" <?= $i ? 'hidden' : '' ?>>
          <div class="hero-film"><img src="<?= esc($slide['image']) ?>" alt="" <?= $i ? 'loading="lazy"' : 'fetchpriority="high"' ?>><?php if (!empty($slide['video'])): ?><video disablepictureinpicture disableremoteplayback muted loop playsinline preload="none" data-src="<?= esc($slide['video']) ?>" aria-hidden="true" tabindex="-1"></video><?php endif; ?></div>
          <div class="hero-copy"><p class="home-kicker"><span></span> <?= esc(SCHOOL_NAME) ?></p>
            <?php if ($i === 0): ?><h1 id="home-title"><?= esc($slide['title']) ?></h1><?php else: ?><h2><?= esc($slide['title']) ?></h2><?php endif; ?>
            <p class="hero-description"><?= esc($slide['description']) ?></p>
            <div class="hero-actions"><a class="pill pill-yellow" href="<?= esc($slide['url']) ?>">Saznajte više <?= icon('arrow-up-right') ?></a><a class="text-link" href="#studiji">Svi studijski programi <?= icon('arrow-right') ?></a></div>
          </div>
          <a class="hero-programme hero-accreditation" href="/Akreditacija" aria-label="Akreditirani studiji — pogledajte akreditaciju"><?= icon('certificate') ?><span class="accreditation-label"><strong>Akreditirani studiji</strong><small>Diplome priznate u EU</small></span><?= icon('arrow-up-right') ?></a>
        </div><?php endforeach; ?>
        <div class="slider-controls" hidden>
          <button type="button" data-prev aria-label="Prethodni slajd"><?= icon('chevron-left') ?></button>
          <div class="slider-dots"><?php foreach ($slides as $i => $slide): ?><button type="button" data-slide="<?= $i ?>" aria-label="Slajd <?= $i + 1 ?>: <?= esc($slide['title']) ?>" <?= $i === 0 ? 'aria-current="true"' : '' ?>></button><?php endforeach; ?></div>
          <button type="button" data-next aria-label="Sljedeći slajd"><?= icon('chevron-right') ?></button>
          <button type="button" data-pause aria-label="Pauziraj izmjenu slajdova"><?= icon('player-pause') ?></button>
        </div>
      </section>
      <div class="home-width quick-dock"><a href="/Novost?id=1473"><span class="dock-icon"><?= icon('arrow-up-right') ?></span><span><strong>Upisi na CEPS</strong>Informacije za buduće studente</span><b><?= icon('arrow-right') ?></b></a><a href="<?= esc(schoolLink('/Placanje-u-ratama')) ?>"><span class="dock-icon"><?= icon('credit-card') ?></span><span><strong>Plaćanje u ratama</strong>Planirajte svoje školovanje</span><b><?= icon('arrow-right') ?></b></a><a href="<?= esc(schoolLink('/360-virtual-tour')) ?>"><span class="dock-icon"><?= icon('view-360') ?></span><span><strong>Upoznajte CEPS</strong>360° virtualna šetnja</span><b><?= icon('arrow-right') ?></b></a></div>

      <div class="home-width study-news-layout"><section id="studiji" class="home-section"><div class="section-heading"><div><p class="home-kicker">PRONAĐITE SVOJ SMJER</p><h2>Studirajte ono što vas <span>pokreće.</span></h2></div><a class="text-link" href="<?= esc(schoolLink('/Studiji')) ?>">Svi studijski programi <?= icon('arrow-up-right') ?></a></div>
        <div class="study-grid">
          <?php $studyTracks = [
            'pravne-nauke.aspx' => ['Opće pravo'],
            'StudijPromet.aspx' => ['Cestovni promet', 'Željeznički promet'],
            'StudijEnergetika.aspx' => ['Gospodarenje energijom i energetska učinkovitost', 'Obnovljivi izvori energije', 'Održavanje energetskih postrojenja', 'Termotehnika'],
            'StudijSigurnost.aspx' => ['Kriminalistika'],
            'StudijPE.aspx' => ['Financije i računovodstvo', 'Menadžment', 'Menadžment javnog sektora i uprave'],
            'StudijIT.aspx' => ['Informacijski sistemi i tehnologije'],
            'StudijZdravstvo.aspx' => ['Sestrinstvo', 'Sanitarni inženjering', 'Fizioterapija', 'Gerijatrija', 'Nutricionizam i dijetetika'],
            'StudijZastita.aspx' => ['Zaštita na radu', 'Zaštita od požara', 'Upravljanje rizicima od katastrofa'],
            'StudijEng.aspx' => ['Management'],
          ]; foreach ($studies as [$url,$title,$image,$number]): ?>
          <a class="study-card study-colour-card" href="<?= esc(schoolLink('/' . $url)) ?>">
            <div class="study-card-copy"><span class="study-icon"><?= studyIcon($url) ?></span><h3><?= esc($url === 'StudijEng.aspx' ? 'Business economics' : $title) ?></h3>
              <ul class="study-tracks"><?php foreach ($studyTracks[$url] as $track): ?><li><?= esc($track) ?></li><?php endforeach; ?></ul>
              <?php if ($url === 'StudijEng.aspx'): ?><span class="study-language">Studij na engleskom jeziku</span><?php endif; ?>
              <span class="study-go" aria-hidden="true"><?= icon('arrow-up-right') ?></span>
            </div>
          </a><?php endforeach; ?>
          <a class="study-card study-all-card" href="/Studiji"><span class="study-all-icon"><?= icon('school') ?></span><strong>Svi studijski programi</strong><span>Pronađite svoj smjer <?= icon('arrow-up-right') ?></span></a>
        </div>
      </section>

      </div>

      <section class="home-width latest-news" aria-labelledby="latest-news-title"><div class="section-heading"><div><p class="home-kicker">IZ ŽIVOTA CEPS-a</p><h2 id="latest-news-title">Novosti i događanja.</h2></div><a class="pill news-archive" href="/Novosti">Arhiva novosti <?= icon('arrow-up-right') ?></a></div>
        <div class="latest-grid"><?php foreach ($news as $row): ?><article class="latest-card"><a class="news-picture" href="/Novost?id=<?= $row['ID'] ?>" tabindex="-1" aria-hidden="true"><img class="news-cover" src="<?= esc(newsImage($row['Tekst'])) ?>" data-fallback="<?= NEWS_DEFAULT_IMAGE ?>" alt="" loading="lazy" width="640" height="400"></a><div class="latest-copy"><time><?= icon('calendar-event') ?> <?= esc(newsDate($row['Datum'])) ?></time><h3><a href="/Novost?id=<?= $row['ID'] ?>"><?= esc(titleText($row['Naslov'])) ?></a></h3><p class="latest-excerpt" hidden><?= esc(excerpt($row['Tekst'])) ?></p><a class="text-link" href="/Novost?id=<?= $row['ID'] ?>">Pročitaj više <?= icon('arrow-up-right') ?></a></div></article><?php endforeach; ?><a class="latest-card latest-archive-card" href="/Novosti"><span class="archive-card-icon"><?= icon('news') ?></span><strong>Arhiva novosti</strong><span>Pregledajte sve novosti i događanja s CEPS-a.</span><span class="archive-card-action">Sve novosti <?= icon('arrow-up-right') ?></span></a></div>
      </section>

      <section class="welcome-section"><div class="home-width welcome-layout"><div><p class="home-kicker">DOBRO DOŠLI!</p><h2>Obrazovanje.<br>Vaša najbolja<br> <span>investicija.</span></h2><a class="pill pill-dark" href="<?= esc(schoolLink('/Onama')) ?>">Upoznajte CEPS <?= icon('arrow-up-right') ?></a></div><div class="welcome-copy"><?php foreach ($copy['welcome'] as $p): ?><p><?= esc($p) ?></p><?php endforeach; ?></div></div></section>

      <section class="home-section home-width"><div class="section-heading"><div><p class="home-kicker">UČENJE KOJE IDE DALJE</p><h2>Više od predavanja.</h2></div></div>
        <div class="opportunity-grid"><article class="campus-card"><video class="campus-video" tabindex="-1" disablepictureinpicture disableremoteplayback autoplay muted loop playsinline preload="metadata" poster="<?= homeAsset('Files/Images/novazgrada.jpg') ?>" aria-label="Video zgrade Visoke škole CEPS"><source src="<?= esc($programmes['Zgrada.aspx']['video']) ?>" type="video/mp4"></video><div><p class="home-kicker">NAŠ KAMPUS</p><h3>Nova zgrada CEPS-a</h3><?php foreach ($programmes['Zgrada.aspx']['paragraphs'] as $p): ?><p><?= esc($p) ?></p><?php endforeach; ?><a class="text-link" href="<?= esc(schoolLink('/Zgrada')) ?>">Upoznajte prostor <?= icon('arrow-up-right') ?></a></div></article>
        <article class="cisco-card"><img class="cisco-brand" src="https://ceps.edu.ba/Files/Images/cisco.jpg" alt="Cisco" width="1920" height="500" loading="lazy"><p class="home-kicker">ZNANJE BEZ GRANICA</p><h3>Cisco akademija.</h3><?php foreach ($programmes['Cisco.aspx']['paragraphs'] as $p): ?><p><?= esc($p) ?></p><?php endforeach; ?><a class="pill pill-yellow" href="<?= esc(schoolLink('/Cisco')) ?>">Saznajte više <?= icon('arrow-up-right') ?></a></article></div>
        <div class="benefits"><?php foreach (array_chunk($copy['features'], (int) ceil(count($copy['features']) / 2)) as $column): ?><div class="benefits-column"><?php foreach ($column as $feature): ?><details name="ceps-faq"><summary><?= esc($feature['title']) ?><span aria-hidden="true"><?= icon('plus') ?></span></summary><div><?php foreach ($feature['paragraphs'] as $p): ?><p><?= esc($p) ?></p><?php endforeach; ?><?php if (str_starts_with($feature['title'], 'Dodatak diplomi')): ?><a class="diploma-certificate" href="https://ceps.edu.ba/Files/ceps_cert.jpg"><img src="https://ceps.edu.ba/Files/ceps_cert.jpg" alt="TÜV Austria certifikati CEPS-a – ISO 9001 i ISO 45001" loading="lazy"></a><?php endif; ?></div></details><?php endforeach; ?></div><?php endforeach; ?></div>
      </section>



      <section class="home-section home-width"><div class="section-heading"><div><p class="home-kicker">KVALITETA I MOGUĆNOSTI</p><h2>Važni dokumenti.<br><span class="documents-heading-accent">Na jednom mjestu.</span></h2></div></div>
        <div class="documents-grid"><?php
        $groups = ['Akreditacija i certifikati'=>[], 'Nostrifikacija diplome'=>[], 'Studentske Erasmus+ prakse'=>[], 'Natječaji / Konkursi'=>[]];
        foreach ($copy['documents'] as $d) {
            $category = stripos($d['title'],'Nostrifikacija') !== false ? 'Nostrifikacija diplome' : (stripos($d['url'],'/Erasmus/') !== false || stripos($d['title'],'Erasmus povelja') !== false ? 'Studentske Erasmus+ prakse' : (stripos($d['title'],'Natječaj') !== false ? 'Natječaji / Konkursi' : 'Akreditacija i certifikati'));
            $groups[$category][] = $d;
        }
        $documentIcons = ['Akreditacija i certifikati'=>'certificate', 'Nostrifikacija diplome'=>'school', 'Studentske Erasmus+ prakse'=>'world', 'Natječaji / Konkursi'=>'clipboard-list'];
        foreach ($groups as $label=>$documents): ?><details class="document-group" name="home-documents"><summary><span class="document-symbol"><?= icon($documentIcons[$label]) ?></span><span class="document-label"><?= esc($label) ?></span><span class="document-count"><?= count($documents) ?></span><span class="document-toggle"><?= icon('plus') ?></span></summary><ul><?php foreach ($documents as $d): ?><li><a href="<?= esc(homeAsset($d['url'])) ?>"><?= esc($d['title']) ?> <?= icon('arrow-up-right') ?></a></li><?php endforeach; ?></ul></details><?php endforeach; ?></div>
      </section>

      <section class="home-width visit-section"><video class="visit-background-video" autoplay muted loop playsinline preload="metadata" disablepictureinpicture disableremoteplayback aria-hidden="true" tabindex="-1" poster="<?= homeAsset('Files/Images/novazgrada.jpg') ?>"><source src="<?= esc($programmes['Zgrada.aspx']['video']) ?>" type="video/mp4"></video><div class="visit-copy"><p class="home-kicker">POGLEDAJTE I UPOZNAJTE NAS</p><h2>Vaše mjesto<br>za novi početak.</h2><a class="pill pill-yellow" href="<?= esc(schoolLink('/360-virtual-tour')) ?>">360° virtualna šetnja <?= icon('arrow-up-right') ?></a></div><div class="visit-links"><a href="<?= esc(str_replace('youtube.com/embed/', 'youtube.com/watch?v=', $copy['videos'][0])) ?>"><span class="play-symbol" aria-hidden="true"><?= icon('player-play') ?></span><span>CEPS – Centar za poslovne studije<strong>Kiseljak, 2020.</strong></span><b aria-hidden="true"><?= icon('arrow-up-right') ?></b></a><a href="<?= esc(str_replace('youtube.com/embed/', 'youtube.com/watch?v=', $copy['videos'][1])) ?>"><span class="play-symbol" aria-hidden="true"><?= icon('player-play') ?></span><span>Izgradnja zgrade Visoke škole CEPS<strong>2016. – 2017.</strong></span><b aria-hidden="true"><?= icon('arrow-up-right') ?></b></a><a href="<?= esc(schoolLink('/360-virtual-tour')) ?>"><span class="play-symbol" aria-hidden="true"><?= icon('view-360') ?></span><span>Posjetite nas<strong>Virtualni pogled na CEPS</strong></span><b aria-hidden="true"><?= icon('arrow-up-right') ?></b></a></div></section>
      <div class="home-width partner-line"><a href="https://www.novotel.ba"><img src="https://www.novotel.ba/Files/novotel-wide.png" alt="Novotel" width="728" height="90" loading="lazy"></a></div>
    </div>
    <?php return ob_get_clean();
}
