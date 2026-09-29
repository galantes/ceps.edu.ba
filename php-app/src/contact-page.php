<?php
declare(strict_types=1);
function contactPage(): string {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','cookie_secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
    $_SESSION['contact_csrf'] ??= bin2hex(random_bytes(32));
    $from = getenv('CEPS_CONTACT_FROM') ?: '';
    $enabled = getenv('CEPS_CONTACT_ENABLED') === '1' && filter_var($from, FILTER_VALIDATE_EMAIL) && !preg_match('/[\r\n]/', $from);
    $values = ['name'=>'','email'=>'','subject'=>'','message'=>'']; $error = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach ($values as $key=>$_) $values[$key] = is_string($_POST[$key] ?? null) ? trim($_POST[$key]) : '';
        $token = is_string($_POST['csrf'] ?? null) ? $_POST['csrf'] : '';
        if (!hash_equals($_SESSION['contact_csrf'], $token)) $error = 'Obrazac je istekao. Osvježite stranicu i pokušajte ponovno.';
        elseif (!empty($_POST['website']) || time() - ($_SESSION['contact_last'] ?? 0) < 60) $error = 'Pričekajte minutu prije ponovnog slanja.';
        elseif (!$values['name'] || mb_strlen($values['name'])>100 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email'])>254 || preg_match('/[\r\n]/', $values['email']) || mb_strlen($values['subject'])>150 || mb_strlen($values['message'])<10 || mb_strlen($values['message'])>5000) $error = 'Provjerite ime, e-mail i poruku (10–5000 znakova).';
        elseif (!$enabled) $error = 'Slanje obrasca trenutno nije dostupno. Pišite nam na info@ceps.edu.ba.';
        else {
            $_SESSION['contact_last'] = time();
            $body = "Ime i prezime: {$values['name']}\nE-mail: {$values['email']}\nTema: {$values['subject']}\n\n{$values['message']}";
            $sent = mail('info@ceps.edu.ba', 'Upit s CEPS web stranice', $body, ['From'=>$from,'Reply-To'=>$values['email'],'Content-Type'=>'text/plain; charset=UTF-8']);
            if ($sent) { $_SESSION['contact_sent']=true; $_SESSION['contact_csrf']=bin2hex(random_bytes(32)); header('Location: /kontakt#contact-form', true, 303); exit; }
            $error = 'Poruku nije bilo moguće poslati. Pokušajte kasnije ili pišite na info@ceps.edu.ba.';
        }
    }
    $success = $_SESSION['contact_sent'] ?? false; unset($_SESSION['contact_sent']);
    $source = contentPages()['kontakt']['html'];
    preg_match('~https://www.google.com/maps/embed\?[^"<]+~', $source, $map);
    ob_start(); ?>
    <div class="institution-page contact-page">
      <div class="institution-width breadcrumbs"><a href="/">Naslovnica</a><?= icon('chevron-right') ?><span>Kontakt</span></div>
      <header class="content-page-heading institution-width study-photo-heading"><img class="study-heading-image" src="https://ceps.edu.ba/Files/Images/2492020.jpg" alt="" fetchpriority="high"><p class="home-kicker">TU SMO ZA VAŠA PITANJA</p><h1>Kontakt</h1><span class="heading-rule"></span><p>Informacije o studiranju, upisima i Visokoj školi CEPS u Kiseljaku.</p></header>
      <div class="institution-width contact-layout">
        <section class="institution-content contact-info"><h2>Razgovarajte s nama</h2>
          <div class="contact-detail"><?= icon('phone') ?><div><h3>Telefoni</h3><?php foreach(['+387 30 875 208','+387 30 875 221','+387 63 653 111','+387 63 473 333','+387 62 408 322'] as $phone): ?><a href="tel:<?= str_replace(' ','',$phone) ?>"><?= $phone ?></a><?php endforeach; ?><small>Fax: +387 30 875 245</small></div></div>
          <div class="contact-detail"><?= icon('mail') ?><div><h3>E-mail</h3><a href="mailto:info@ceps.edu.ba">info@ceps.edu.ba</a></div></div>
          <div class="contact-detail"><?= icon('clock') ?><div><h3>Studentska služba</h3><p>Ponedjeljak – petak: <strong>08:00 – 17:00</strong><br>Subota: <strong>08:00 – 14:00</strong></p></div></div>
          <div class="contact-detail"><?= icon('map-pin') ?><div><h3>Posjetite CEPS</h3><p>Ul. Josipa bana Jelačića bb<br>71250 Kiseljak, Bosna i Hercegovina</p><a href="#contact-map">Pogledajte lokaciju <?= icon('arrow-down') ?></a></div></div>
          <details class="contact-official"><summary>Podaci ustanove <?= icon('chevron-down') ?></summary><p><?= esc(SCHOOL_NAME) ?></p><p>Žiro račun: <strong>3383402249882310</strong><br>UniCredit Bank d.d. BiH</p><a href="/Maticni_podaci">Svi matični podaci <?= icon('arrow-up-right') ?></a></details>
        </section>
        <section class="institution-content contact-form-card" id="contact-form"><h2>Pošaljite nam upit</h2><p>Ostavite svoje podatke i pitanje. Polja označena zvjezdicom su obavezna.</p>
          <?php if($success): ?><p class="contact-notice" role="status">Vaša poruka predana je na slanje. Hvala na upitu.</p><?php endif; ?>
          <?php if($error): ?><p class="contact-error" role="alert"><?= esc($error) ?></p><?php endif; ?>
          <?php if(!$enabled): ?><p class="contact-notice">Slanje obrasca trenutno nije dostupno. Za upite koristite <a href="mailto:info@ceps.edu.ba">info@ceps.edu.ba</a> ili nas nazovite.</p><?php endif; ?>
          <form action="/Kontakt#contact-form" method="post"><input type="hidden" name="csrf" value="<?= esc($_SESSION['contact_csrf']) ?>"><div class="contact-trap" aria-hidden="true"><label>Web stranica<input name="website" tabindex="-1" autocomplete="off"></label></div>
            <label for="contact-name">Ime i prezime *</label><input id="contact-name" name="name" autocomplete="name" maxlength="100" required value="<?= esc($values['name']) ?>">
            <label for="contact-email">E-mail *</label><input id="contact-email" name="email" type="email" autocomplete="email" maxlength="254" required value="<?= esc($values['email']) ?>">
            <label for="contact-subject">Tema</label><input id="contact-subject" name="subject" maxlength="150" value="<?= esc($values['subject']) ?>">
            <label for="contact-message">Poruka *</label><textarea id="contact-message" name="message" rows="6" minlength="10" maxlength="5000" required><?= esc($values['message']) ?></textarea>
            <button class="pill pill-yellow" type="submit" <?= $enabled?'':'disabled' ?>><span>Pošalji upit</span><?= icon('send') ?></button>
          </form>
        </section>
      </div>
      <?php if($map): ?><section class="institution-width contact-map" id="contact-map"><h2>Pronađite nas u Kiseljaku</h2><iframe src="<?= esc(html_entity_decode($map[0], ENT_QUOTES, 'UTF-8')) ?>" title="Lokacija Visoke škole CEPS" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></section><?php endif; ?>
    </div>
    <?php return ob_get_clean();
}
