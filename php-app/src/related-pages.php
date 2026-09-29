<?php
declare(strict_types=1);

function relatedPagesSidebar(array $page, array $toc): string {
    $groups = [
        [['Akreditacija','Kvaliteta','Akti','Bolonjski_proces'], 'Kvaliteta i povjerenje', ['Akreditacija','Kvaliteta','Akti','Bolonjski_proces']],
        [['Misija','Akademska_organizacija','Maticni_podaci'], 'Upoznajte ustanovu', ['Misija','Akademska_organizacija','Maticni_podaci','Akreditacija']],
        [['Studiji','Upisi','Placanje-u-ratama','Maturanti'], 'Planirajte svoj studij', ['Studiji','Upisi','Placanje-u-ratama','Maturanti']],
        [['Knjiznica','DIT','istrazivacki-centar','Projekti','centar-za-klimu-i-odrzivost'], 'Znanje i istraživanje', ['istrazivacki-centar','Projekti','DIT','Knjiznica']],
        [['Cisco','Cjelozivotno_ucenje','prijava-andragoska-obuka','Alumni'], 'Učenje i razvoj', ['Cjelozivotno_ucenje','Cisco','prijava-andragoska-obuka','Alumni']],
        [['Medunarodna_suradnja','Alerrt','Crase'], 'Suradnja i projekti', ['Medunarodna_suradnja','Alerrt','Crase','Projekti']],
        [['Zgrada','Galerija','360-virtual-tour','Kontakt'], 'Istražite CEPS', ['Zgrada','360-virtual-tour','Galerija','Kontakt']],
    ];
    $info = [
        'Akreditacija'=>['certificate','Status i potvrde ustanove'],
        'Kvaliteta'=>['shield-check','Sustav osiguranja kvalitete'],
        'Akti'=>['files','Dokumenti i tijela ustanove'],
        'Bolonjski_proces'=>['school','Kako je organizirano studiranje'],
        'Misija'=>['target','Naše vrijednosti i ciljevi'],
        'Akademska_organizacija'=>['hierarchy','Ustroj akademske zajednice'],
        'Maticni_podaci'=>['id','Službeni podaci ustanove'],
        'Studiji'=>['school','Pronađite svoj smjer'],
        'Upisi'=>['pencil','Informacije za buduće studente'],
        'Placanje-u-ratama'=>['credit-card','Mogućnosti plaćanja studija'],
        'Maturanti'=>['users','Radionica za buduće studente'],
        'istrazivacki-centar'=>['microscope','Istraživačke aktivnosti CEPS-a'],
        'Projekti'=>['briefcase','Inicijative i projektne aktivnosti'],
        'DIT'=>['book','Društvena i tehnička istraživanja'],
        'Knjiznica'=>['books','Literatura i izdavaštvo'],
        'Cjelozivotno_ucenje'=>['bulb','Nove vještine i usavršavanje'],
        'Cisco'=>['network','Akademija mrežnih tehnologija'],
        'prijava-andragoska-obuka'=>['forms','Prijava za osnovnu obuku'],
        'Alumni'=>['users-group','Ostanite povezani s CEPS-om'],
        'Medunarodna_suradnja'=>['world','Mobilnosti, aktivnosti i sporazumi'],
        'Alerrt'=>['shield','Program ALERRT'],
        'Crase'=>['shield-check','Program CRASE'],
        'Zgrada'=>['building','Upoznajte prostor za studiranje'],
        '360-virtual-tour'=>['view-360','Prošetajte kampusom online'],
        'Galerija'=>['photo','CEPS kroz fotografije'],
        'Kontakt'=>['map-pin','Lokacija i kontakt podaci'],
    ];
    $label = 'Uz vaš studij';
    $slugs = ['Upisi','Placanje-u-ratama','Knjiznica'];
    foreach ($groups as [$members, $heading, $links]) {
        if (in_array($page['slug'], $members, true)) { $label = $heading; $slugs = $links; break; }
    }
    $slugs = array_values(array_unique(array_merge([$page['slug']], $slugs)));
    $toc = array_values(array_filter($toc, fn($item) => html_entity_decode($item['title'], ENT_QUOTES, 'UTF-8') !== $page['title']));
    $pages = contentPages();
    ob_start(); ?>
    <aside class="institution-menu contextual-menu" aria-label="Povezani sadržaji">
      <p class="related-eyebrow"><?= esc($label) ?></p>
      <nav class="related-links" aria-label="Povezane stranice">
        <?php foreach ($slugs as $slug): $target = $pages[strtolower($slug)] ?? null; if (!$target) continue; [$glyph, $description] = $info[$slug] ?? ['file-text', '']; ?>
        <a href="/<?= esc($target['slug']) ?>" <?= $slug === $page['slug'] ? 'aria-current="page"' : '' ?>><?= icon($glyph) ?><span><?= esc($target['title']) ?></span></a>
        <?php endforeach; ?>
      </nav>
      <?php if(count($toc) >= 2): ?><details class="page-toc related-toc"><summary>Na ovoj stranici <?= icon('chevron-down') ?></summary><nav aria-label="Sadržaj stranice"><?php foreach($toc as $item): ?><a href="#<?= esc($item['id']) ?>"><?= icon('chevron-right') ?><?= esc($item['title']) ?></a><?php endforeach; ?></nav></details><?php endif; ?>
      <?php if(!in_array('Kontakt', $slugs, true)): ?><a class="related-help" href="/Kontakt"><?= icon('mail') ?><span>Kontakt</span></a><?php endif; ?>
    </aside>
    <?php return ob_get_clean();
}
