<?php
declare(strict_types=1);
require_once __DIR__ . '/icons.php';

function schoolLink(string $path): string {
    $pagesPath = dirname(__DIR__) . '/content/pages.json';
    static $migrated;
    $migrated ??= json_decode(file_get_contents($pagesPath), true);
    $key = strtolower(preg_replace('/\.aspx$/i', '', ltrim($path, '/')));
    if (isset($migrated[$key])) return '/' . $migrated[$key]['slug'];
    return in_array($path, ['/', '/Novosti', '/Onama'], true) ? $path : 'https://www.ceps.edu.ba' . $path;
}

function schoolNavigation(): string {
    $groups = [
        'O nama' => [
            '/Onama'=>'O ustanovi', '/Akreditacija'=>'Akreditacija',
            '/Akademska_organizacija'=>'Akademska organizacija', '/Misija'=>'Misija, vizija i ciljevi',
            '/Kvaliteta'=>'Osiguranje kvalitete', '/Akti'=>'Akti i tijela ustanove',
            '/Maticni_podaci'=>'Matični podaci', '/Bolonjski_proces'=>'Bolonjski proces',
            '/Knjiznica'=>'Knjižnica i izdavaštvo', '/DIT'=>'Društvena i tehnička istraživanja',
            '/Alumni'=>'Alumni asocijacija', '/Cjelozivotno_ucenje'=>'Cjeloživotno učenje',
            '/istrazivacki-centar'=>'Istraživački centar', '/centar-za-klimu-i-odrzivost'=>'Centar za klimu i održivost',
        ],
        'Studiji' => [
            '/Studiji'=>'Svi studijski programi', '/StudijPE'=>'Poslovna ekonomija',
            '/StudijIT'=>'Informacijske tehnologije', '/StudijZdravstvo'=>'Zdravstvene studije',
            '/StudijPromet'=>'Promet', '/StudijEnergetika'=>'Energetika',
            '/pravne-nauke'=>'Pravne nauke', '/StudijSigurnost'=>'Studij sigurnosti',
            '/StudijZastita'=>'Zaštita na radu i zaštita od požara', '/StudijEng'=>'Studij na engleskom jeziku',
        ],
        'Međunarodna suradnja' => [
            '/Medunarodna_suradnja'=>'Suradnja i sporazumi',
            '/Files/PDF/ceps_povelja_2023.pdf?v=1'=>'Erasmus povelja',
            '/Alerrt'=>'CEPS – Alerrt Center', '/Crase'=>'CRASE',
        ],
    ];
    $menuIcons = ['O nama'=>'building-bank', 'Studiji'=>'school', 'Međunarodna suradnja'=>'world', 'Projekti'=>'briefcase', 'Galerija'=>'photo', 'Novosti'=>'news', 'Kontakt'=>'mail'];
    $normalize = static fn(string $url): string => strtolower(rtrim(preg_replace('/\.aspx$/i', '', parse_url($url, PHP_URL_PATH) ?: '/'), '/')) ?: '/';
    $current = $normalize(seoInternalPath(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
    if (preg_match('~^/novosti/[0-9]+~', $current)) $current = '/novost';
    $html = '<a href="' . schoolLink('/') . '"' . ($current === '/' ? ' aria-current="page"' : '') . '>' . icon('home') . 'Naslovnica</a>';
    foreach ($groups as $label => $links) {
        $active = in_array($current, array_map($normalize, array_keys($links)), true);
        if ($label === 'Studiji' && in_array($current, ['/upisi','/placanje-u-ratama','/maturanti','/cisco'], true)) $active = true;
        if ($label === 'O nama' && in_array($current, ['/zgrada','/360-virtual-tour','/prijava-andragoska-obuka'], true)) $active = true;
        $html .= '<details class="nav-group'.($active ? ' nav-active' : '').'"><summary'.($active ? ' aria-current="true"' : '').'>' . icon($menuIcons[$label]) . esc($label) . icon('chevron-down') . '</summary><div class="nav-dropdown">';
        foreach ($links as $path => $name) $html .= '<a href="' . esc(schoolLink($path)) . '"'.($current === $normalize($path) ? ' aria-current="page"' : '').'>' . icon('arrow-up-right') . esc($name) . '</a>';
        $html .= '</div></details>';
    }
    foreach (['/Projekti'=>'Projekti', '/Galerija'=>'Galerija', '/Novosti'=>'Novosti', '/Kontakt'=>'Kontakt'] as $path => $label) {
        $exact = $current === $normalize($path);
        $active = $exact || ($path === '/Projekti' && str_starts_with($current, '/projekt/')) || ($path === '/Novosti' && $current === '/novost');
        $html .= '<a href="' . esc(schoolLink($path)) . '"' . ($active ? ' aria-current="'.($exact ? 'page' : 'true').'"' : '') . '>' . icon($menuIcons[$label]) . esc($label) . '</a>';
    }
    $html .= '<a href="/Pretraga"'.($current === '/pretraga' ? ' aria-current="page"' : '').'>' . icon('search') . 'Pretraga</a>';
    return $html;
}

function schoolHeader(): string {
    $nav = schoolNavigation();
    return '<header class="school-header">
      <div class="utility"><div class="shell-width"><span class="utility-name">' . esc(SCHOOL_NAME) . '</span><nav aria-label="Brze poveznice"><a href="https://euniversity.ba">' . icon('school') . ' eUniversity</a><a href="https://outlook.com/ceps.edu.ba">' . icon('mail') . ' Webmail</a><a href="https://ceps.edu.ba/Files/2026/04/vodic_2026.pdf?v=1">' . icon('book-2') . ' eVodič</a><a href="http://en.ceps.edu.ba" lang="en">EN</a><a href="http://de.ceps.edu.ba" lang="de">DE</a></nav></div></div>
      <div class="identity shell-width"><a class="school-logo" href="' . schoolLink('/') . '"><img src="https://ceps.edu.ba/Files/2026/logo_2026_17.png" width="396" height="100" alt="' . esc(SCHOOL_NAME) . ' – Za one koji žele više!"></a><div class="header-name">' . esc(SCHOOL_NAME) . '</div><div class="identity-actions"><a class="school-contact" href="tel:+38730875208">' . icon('phone') . '<span><small>Studentska služba</small>+387 30 875 208</span></a><a class="enrol" href="' . schoolLink('/Upisi') . '"><span class="button-label">Upisi na CEPS</span> ' . icon('arrow-up-right') . '</a></div></div>
      <div class="navigation-wrap"><nav class="desktop-navigation shell-width" aria-label="Glavna navigacija">' . $nav . '</nav><details class="mobile-navigation"><summary>' . icon('menu-2') . ' Izbornik ' . icon('chevron-down') . '</summary><nav aria-label="Mobilna navigacija">' . $nav . '</nav></details></div>
    </header>';
}

function contactWidget(): string {
    $html = '<aside class="contact-widget" aria-label="Telefonski kontakt"><div class="contact-panel" id="contact-panel" hidden><button class="contact-close" type="button" aria-label="Zatvori telefonske brojeve">' . icon('x') . '</button><p class="contact-caption">TU SMO ZA VAS</p><h2>Pozovite nas.</h2><ul>';
    foreach (['+387 30 875 208', '+387 30 875 221', '+387 63 653 111', '+387 63 473 333', '+387 62 408 322'] as $index => $phone) {
        $html .= '<li><a href="tel:' . str_replace(' ', '', $phone) . '">' . icon($index < 2 ? 'phone' : 'device-mobile') . '<span>' . $phone . '</span>' . icon('arrow-up-right') . '</a></li>';
    }
    return $html . '</ul></div><button class="contact-launcher" type="button" aria-label="Otvori telefonske brojeve" aria-controls="contact-panel" aria-expanded="false">' . icon('phone') . '<span>Pozovite nas</span></button></aside>';
}

function schoolFooter(): string {
    return '<footer class="school-footer">
      <div class="footer-intro shell-width"><div><p class="footer-label">VAŠ SLJEDEĆI KORAK</p><h2 class="ceps-slogan">Za one koji žele <span>više.</span></h2><p>Upoznajte CEPS i pronađite svoj studijski program.</p></div><div class="footer-intro-actions"><a class="pill pill-yellow" href="' . schoolLink('/Upisi') . '"><span class="footer-action-label">Informacije o upisu</span> ' . icon('arrow-up-right') . '</a><a class="footer-ask" href="' . schoolLink('/Kontakt') . '">' . icon('message-circle') . ' <span class="footer-action-label">Razgovarajte s nama</span></a></div></div>
      <div class="footer-grid shell-width">
        <div class="footer-school"><a class="footer-logo" href="' . schoolLink('/') . '"><img src="https://ceps.edu.ba/Files/2026/logo_2026_17.png" width="396" height="100" alt="' . esc(SCHOOL_NAME) . ' – Za one koji žele više!" loading="lazy"></a><p>' . esc(SCHOOL_NAME) . '</p><p>Ul. Josipa bana Jelačića bb<br>71250 Kiseljak, Bosna i Hercegovina</p><a class="footer-contact" href="mailto:info@ceps.edu.ba">info@ceps.edu.ba</a><br><a href="tel:+38730875208">+387 30 875 208</a></div>
        <div><h3>Studiranje na CEPS-u</h3><a href="' . schoolLink('/Studiji') . '">Studijski programi</a><a href="' . schoolLink('/Upisi') . '">Informacije o upisu</a><a href="' . schoolLink('/Placanje-u-ratama') . '">Plaćanje u ratama</a><a href="https://euniversity.ba">eUniversity</a><a href="https://www.ceps.edu.ba/Files/2026/04/letak_2026_web.pdf">Preuzmi eBrošuru</a><a href="' . schoolLink('/360-virtual-tour') . '">Virtualna šetnja</a></div>
        <div><h3>Ustanova</h3><a href="' . schoolLink('/Onama') . '">O nama</a><a href="' . schoolLink('/Akreditacija') . '">Akreditacija</a><a href="' . schoolLink('/Projekti') . '">Projekti</a><a href="' . schoolLink('/Medunarodna_suradnja') . '">Međunarodna suradnja</a><a href="/Novosti">Novosti</a><a href="' . schoolLink('/Kontakt') . '">Kontakt</a></div>
        <div><h3>Studentska služba</h3><p>Ponedjeljak – petak<br><strong>08:00 – 17:00</strong></p><p>Subota<br><strong>08:00 – 14:00</strong></p><a class="footer-accreditation" href="' . schoolLink('/Akreditacija') . '">' . icon('certificate') . '<span><strong>Akreditirani studiji</strong><small>Diplome priznate u EU</small></span>' . icon('arrow-up-right') . '</a></div>
        </div>
        <div class="footer-connect shell-width"><div><strong>Budimo povezani.</strong><p>Novosti, događanja i studentski život na CEPS-u.</p></div><div class="social-links"><a href="https://bs-ba.facebook.com/CEPS.Kiseljak">' . icon('brand-facebook') . '<span>Facebook</span></a><a href="https://www.instagram.com/ceps_kiseljak/">' . icon('brand-instagram') . '<span>Instagram</span></a><a href="https://www.youtube.com/channel/UCpJ6XmkNs8bSoTYrSrYn9xA">' . icon('brand-youtube') . '<span>YouTube</span></a></div></div>
        <div class="footer-bottom shell-width"><span>© ' . date('Y') . ' ' . esc(SCHOOL_NAME) . '</span><a href="#" class="footer-top">Na vrh ' . icon('arrow-up') . '</a></div></footer>';
}
