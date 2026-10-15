<?php
declare(strict_types=1);

function newsFooter(array $row): string {
    $title = titleText($row['Naslov']);
    $url = siteOrigin() . seoUrl('/Novost?id=' . $row['ID']);
    $encoded = rawurlencode($url);
    $date = $row['Datum'] ?? '';
    ob_start(); ?>
    <section id="news-info" class="news-footer" aria-label="Informacije i dijeljenje novosti">
      <div class="news-meta">
        <img class="news-author-photo" src="https://ceps.edu.ba/Files/Images/2492020.jpg" alt="Zgrada CEPS-a" width="48" height="48" loading="lazy">
        <div class="news-author-copy"><span><?php if ($date): ?><time datetime="<?= esc((new DateTimeImmutable($date))->format('Y-m-d')) ?>"><?= esc(newsDate($date)) ?></time><?php endif ?></span></div>
      </div>
      <div class="news-share" data-share-url="<?= esc($url) ?>" data-share-title="<?= esc($title) ?>">
        <p>Podijeli</p>
        <div class="news-share-actions">
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $encoded ?>" aria-label="Podijeli na Facebooku" title="Podijeli na Facebooku"><?= icon('brand-facebook') ?><span>Facebook</span></a>
          <a href="https://wa.me/?text=<?= rawurlencode($title . ' ' . $url) ?>" aria-label="Podijeli na WhatsAppu" title="Podijeli na WhatsAppu"><?= icon('brand-whatsapp') ?><span>WhatsApp</span></a>
          <a href="mailto:?subject=<?= rawurlencode($title) ?>&amp;body=<?= $encoded ?>" aria-label="Podijeli e-mailom" title="Podijeli e-mailom"><?= icon('mail') ?><span>E-mail</span></a>
          <button type="button" data-copy-link aria-label="Kopiraj link" title="Kopiraj link" hidden><?= icon('link') ?><span>Kopiraj link</span></button>
          <button type="button" data-print-news aria-label="Ispiši novost" title="Ispiši novost" hidden><?= icon('printer') ?><span>Ispiši</span></button>
        </div>
        <p class="news-share-status" role="status" aria-live="polite"></p>
      </div>
      <a class="news-return" href="/Novosti"><?= icon('arrow-left') ?><span>Povratak na novosti</span></a>
    </section>
    <aside class="news-partner" aria-label="Novotel">
      <a href="https://www.novotel.ba"><img src="https://www.novotel.ba/Files/novotel-wide.png" alt="Novotel — posjetite web stranicu" width="728" height="90" loading="lazy"></a>
    </aside>
    <?php return ob_get_clean();
}

