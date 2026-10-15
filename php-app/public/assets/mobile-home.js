(() => {
  const english = document.documentElement.lang === 'en';
  const german = document.documentElement.lang === 'de';
  const mobile = matchMedia('(max-width: 700px)');
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  document.querySelectorAll('.home .study-grid:not([data-study-carousel]), .home .latest-grid').forEach((track, index) => {
    track.id = `home-carousel-${index}`;
    const controls = document.createElement('div');
    controls.className = 'mobile-carousel-controls';
    [-1, 1].forEach(direction => {
      const button = document.createElement('button');
      button.type = 'button';
      button.setAttribute('aria-label', german ? `${direction < 0 ? 'Vorherige' : 'Nächste'} ${track.matches('.study-grid') ? 'Studiengänge' : 'Nachrichten'}` : (english ? `${direction < 0 ? 'Previous' : 'Next'} ${track.matches('.study-grid') ? 'study cards' : 'news'}` : `${direction < 0 ? 'Prethodne' : 'Sljedeće'} ${track.matches('.study-grid') ? 'studijske kartice' : 'novosti'}`));
      button.setAttribute('aria-controls', track.id);
      button.innerHTML = `<i class="ti ti-chevron-${direction < 0 ? 'left' : 'right'}" aria-hidden="true"></i>`;
      button.addEventListener('click', () => track.scrollBy({ left: direction * (track.firstElementChild.getBoundingClientRect().width + 14), behavior: reduced.matches ? 'instant' : 'smooth' }));
      controls.append(button);
    });
    track.after(controls);
  });
  document.querySelectorAll('.welcome-copy, .campus-card > div').forEach((container, index) => {
    const paragraphs = [...container.children].filter(node => node.matches('p:not(.home-kicker)'));
    if (!paragraphs.length) return;
    const text = document.createElement('div');
    text.className = 'mobile-readable';
    text.id = `home-readable-${index}`;
    paragraphs[0].before(text);
    paragraphs.forEach(paragraph => text.append(paragraph));
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'mobile-read-more';
    button.setAttribute('aria-controls', text.id);
    let expanded = false;
    function update() {
      text.classList.toggle('is-collapsed', mobile.matches && !expanded);
      button.hidden = !mobile.matches;
      button.setAttribute('aria-expanded', String(expanded));
      button.textContent = german ? (expanded ? 'Weniger anzeigen' : 'Weiterlesen') : (expanded ? (english ? 'Show less' : 'Prikaži manje') : (english ? 'Read more' : 'Pročitaj više'));
    }
    button.addEventListener('click', () => { expanded = !expanded; update(); });
    text.after(button);
    mobile.addEventListener('change', update);
    update();
  });
})();
