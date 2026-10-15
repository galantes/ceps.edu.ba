(() => {
  const english = document.documentElement.lang === 'en';
  const german = document.documentElement.lang === 'de';
  const article = document.querySelector('[data-news-id] .article-body');
  if (article) {
    const photos = [...article.querySelectorAll('img')].filter(img => {
      const link = img.closest('a');
      return !/\.(?:svg|gif)(?:[?#]|$)|(?:^|\/)pdf\.png(?:[?#]|$)/i.test(img.src) && (!link || /\.(?:jpe?g|png|webp)(?:[?#]|$)/i.test(link.href));
    });
    if (photos.length > 3) {
      const gallery = document.createElement('section');
      gallery.className = 'news-photo-gallery';
      gallery.setAttribute('aria-label', german ? 'Fotogalerie' : (english ? 'Photo gallery' : 'Galerija fotografija'));
      const heading = document.createElement('h2');
      heading.textContent = `${german ? 'Fotogalerie' : (english ? 'Photo gallery' : 'Galerija fotografija')} (${photos.length})`;
      const grid = document.createElement('div');
      grid.className = 'news-photo-grid';
      gallery.append(heading, grid);
      article.append(gallery);
      photos.forEach((img, i) => {
        const original = img.closest('a');
        const parent = img.parentElement;
        const link = document.createElement('a');
        link.href = original ? original.href : img.src;
        link.setAttribute('aria-label', german ? `Foto ${i + 1} von ${photos.length} öffnen` : (english ? `Open photo ${i + 1} of ${photos.length}` : `Otvori fotografiju ${i + 1} od ${photos.length}`));
        img.removeAttribute('width'); img.removeAttribute('height');
        const thumbnail = img.cloneNode(true);
        if (i < 3) thumbnail.loading = 'eager';
        link.append(thumbnail);
        img.remove();
        if (i >= 3) link.hidden = true;
        if (i === 2 && photos.length > 3) {
          const badge = document.createElement('span');
          badge.className = 'news-photo-more';
          badge.textContent = german ? `+${photos.length - 3} Fotos` : (english ? `+${photos.length - 3} photos` : `+${photos.length - 3} fotografija`);
          link.append(badge);
        }
        grid.append(link);
        let empty = parent;
        while (empty && empty !== article && !empty.textContent.trim() && !empty.querySelector('img,video,iframe,table')) {
          const next = empty.parentElement; empty.remove(); empty = next;
        }
      });
    }
  }
  const page = document.querySelector('[data-news-id], [data-page="Galerija"],[data-page="Alerrt"],[data-page="Crase"]');
  if (!page) return;
  const links = [...page.querySelectorAll('.legacy-content a, .news-photo-gallery a')].filter(link => link.querySelector('img') && /\.(jpe?g|png|webp)(?:[?#]|$)/i.test(link.href));
  if (!links.length) return;
  const dialog = document.createElement('dialog');
  dialog.className = 'gallery-viewer';
  dialog.setAttribute('aria-label', german ? 'Fotoansicht' : (english ? 'Photo viewer' : 'Pregled fotografija'));
  dialog.innerHTML = '<div class="gallery-toolbar"><span role="status"></span><button type="button" data-close aria-label="Zatvori fotografiju"><i class="ti ti-x" aria-hidden="true"></i></button></div><div class="gallery-stage"><button type="button" data-prev aria-label="Prethodna fotografija"><i class="ti ti-chevron-left" aria-hidden="true"></i></button><img alt=""><button type="button" data-next aria-label="Sljedeća fotografija"><i class="ti ti-chevron-right" aria-hidden="true"></i></button></div>';
  if (english) { const labels = ['Close photo', 'Previous photo', 'Next photo']; dialog.querySelectorAll('button').forEach((button, i) => button.setAttribute('aria-label', labels[i])); }
  if (german) { const labels = ['Foto schließen', 'Vorheriges Foto', 'Nächstes Foto']; dialog.querySelectorAll('button').forEach((button, i) => button.setAttribute('aria-label', labels[i])); }
  document.body.append(dialog);
  let index = 0;
  let trigger = null;
  function show(next) {
    index = (next + links.length) % links.length;
    const img = dialog.querySelector('img');
    img.src = links[index].href;
    img.alt = links[index].querySelector('img').alt || (german ? 'Foto der FH CEPS' : (english ? 'CEPS photo' : 'Fotografija CEPS-a'));
    dialog.querySelector('[role="status"]').textContent = `${index + 1} / ${links.length}`;
  }
  links.forEach((link, i) => link.addEventListener('click', event => {
    if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault(); trigger = link; show(i); dialog.showModal();
  }));
  dialog.querySelector('[data-close]').addEventListener('click', () => dialog.close());
  dialog.querySelector('[data-prev]').addEventListener('click', () => show(index - 1));
  dialog.querySelector('[data-next]').addEventListener('click', () => show(index + 1));
  dialog.addEventListener('keydown', event => {
    if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') { event.preventDefault(); show(index + (event.key === 'ArrowRight' ? 1 : -1)); }
  });
  dialog.addEventListener('click', event => { if (event.target === dialog || event.target.classList.contains('gallery-stage')) dialog.close(); });
  dialog.addEventListener('close', () => trigger?.focus({preventScroll:true}));
})();

document.querySelectorAll('.ceps-tv-play').forEach(button => {
  button.addEventListener('click', () => {
    const frame = document.createElement('iframe');
    frame.src = 'https://www.youtube-nocookie.com/embed/' + button.dataset.video + '?autoplay=1';
    frame.title = button.getAttribute('aria-label');
    frame.allow = 'autoplay; encrypted-media; picture-in-picture; web-share';
    frame.allowFullscreen = true;
    frame.referrerPolicy = 'strict-origin-when-cross-origin';
    button.replaceWith(frame);
  });
});
