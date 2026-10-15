<?php
declare(strict_types=1);
function contactPage(): string {
    $source = contentPages()['kontakt']['html'];
    preg_match('~https://www.google.com/maps/embed\?[^"<]+~', $source, $map);
    ob_start(); ?>
    <div class="institution-page contact-page">
      <div class="institution-width breadcrumbs"><a href="/">Naslovnica</a><?= icon('chevron-right') ?><span>Kontakt</span></div>
      <header class="content-page-heading institution-width study-photo-heading"><img class="study-heading-image" src="https://ceps.edu.ba/Files/Images/2492020.jpg" alt="" fetchpriority="high"><p class="home-kicker">TU SMO ZA VAŠA PITANJA</p><h1>Kontakt</h1><span class="heading-rule"></span><p>Informacije o studiranju, upisima i Visokoj školi CEPS u Kiseljaku.</p></header>
      <div class="institution-width contact-layout contact-without-form">
        <section class="institution-content contact-info"><h2>Razgovarajte s nama</h2>
          <div class="contact-detail"><?= icon('phone') ?><div><h3>Telefoni</h3><?php foreach(['+387 30 875 208','+387 30 875 221','+387 63 653 111','+387 63 473 333','+387 62 408 322'] as $phone): ?><a href="tel:<?= str_replace(' ','',$phone) ?>"><?= $phone ?></a><?php endforeach; ?><small>Fax: +387 30 875 245</small></div></div>
          <div class="contact-detail"><?= icon('mail') ?><div><h3>E-mail</h3><a href="mailto:info@ceps.edu.ba">info@ceps.edu.ba</a></div></div>
          <div class="contact-detail"><?= icon('clock') ?><div><h3>Studentska služba</h3><p>Ponedjeljak – petak: <strong>08:00 – 17:00</strong><br>Subota: <strong>08:00 – 14:00</strong></p></div></div>
          <div class="contact-detail"><?= icon('map-pin') ?><div><h3>Posjetite CEPS</h3><p>Ul. Josipa bana Jelačića bb<br>71250 Kiseljak, Bosna i Hercegovina</p><a href="#contact-map">Pogledajte lokaciju <?= icon('arrow-down') ?></a></div></div>
          <details class="contact-official"><summary>Podaci ustanove <?= icon('chevron-down') ?></summary><p><?= esc(SCHOOL_NAME) ?></p><p>Žiro račun: <strong>3383402249882310</strong><br>UniCredit Bank d.d. BiH</p><a href="/Maticni_podaci">Svi matični podaci <?= icon('arrow-up-right') ?></a></details>
        </section>

      </div>
      <?php if($map): ?><section class="institution-width contact-map" id="contact-map"><h2>Pronađite nas u Kiseljaku</h2><iframe src="<?= esc(html_entity_decode($map[0], ENT_QUOTES, 'UTF-8')) ?>" title="Lokacija Visoke škole CEPS" loading="lazy" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></section><?php endif; ?>
    </div>
    <?php return ob_get_clean();
}
