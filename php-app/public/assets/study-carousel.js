(() => {
  const track = document.querySelector('[data-study-carousel]');
  if (!track) return;
  const section = track.closest('section');
  const cards = [...track.children];
  const links = [...section.querySelectorAll('.study-index a')];
  const controls = section.querySelector('.study-carousel-controls');
  const previous = controls.querySelector('[data-study-prev]');
  const next = controls.querySelector('[data-study-next]');
  const position = controls.querySelector('.study-carousel-position');
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  function move(index) {
    const left = cards[index].offsetLeft - cards[0].offsetLeft;
    track.scrollTo({ left, behavior: reduced.matches ? 'instant' : 'smooth' });
  }
  function update() {
    const bounds = track.getBoundingClientRect();
    const visible = cards.map((card, index) => {
      const rect = card.getBoundingClientRect();
      const overlap = Math.min(rect.right, bounds.right) - Math.max(rect.left, bounds.left);
      return overlap > Math.min(rect.width, bounds.width) / 2 ? index : -1;
    }).filter(index => index >= 0);
    links.forEach((link, index) => link.classList.toggle('is-visible', visible.includes(index)));
    if (visible.length) position.textContent = `${visible[0] + 1}${visible.length > 1 ? '–' + (visible.at(-1) + 1) : ''} ${document.documentElement.lang === 'de' ? 'von' : (document.documentElement.lang === 'en' ? 'of' : 'od')} ${cards.length} ${document.documentElement.lang === 'de' ? 'Studiengänge' : (document.documentElement.lang === 'en' ? 'programmes' : 'studija')}`;
    previous.disabled = track.scrollLeft <= 2;
    next.disabled = track.scrollLeft >= track.scrollWidth - track.clientWidth - 2;
  }
  links.forEach((link, index) => link.addEventListener('click', event => {
    event.preventDefault();
    move(index);
  }));
  [previous, next].forEach((button, index) => button.addEventListener('click', () => {
    const step = cards[1].offsetLeft - cards[0].offsetLeft;
    const current = Math.round(track.scrollLeft / step);
    move(Math.max(0, Math.min(cards.length - 1, current + (index ? 1 : -1))));
  }));
  let drag = null, dragged = false;
  track.addEventListener('dragstart', event => event.preventDefault());
  track.addEventListener('pointerdown', event => {
    if (event.pointerType !== 'mouse' || event.button !== 0) return;
    dragged = false;
    drag = { id: event.pointerId, x: event.clientX, left: track.scrollLeft };
  });
  track.addEventListener('pointermove', event => {
    if (!drag || event.pointerId !== drag.id) return;
    const distance = event.clientX - drag.x;
    if (!dragged && Math.abs(distance) < 6) return;
    if (!dragged) {
      dragged = true;
      track.classList.add('is-dragging');
      track.setPointerCapture(event.pointerId);
    }
    event.preventDefault();
    track.scrollLeft = drag.left - distance;
  });
  function endDrag(event) {
    if (!drag || event.pointerId !== drag.id) return;
    drag = null;
    track.classList.remove('is-dragging');
    if (track.hasPointerCapture(event.pointerId)) track.releasePointerCapture(event.pointerId);
  }
  track.addEventListener('pointerup', endDrag);
  track.addEventListener('pointercancel', endDrag);
  track.addEventListener('lostpointercapture', () => {
    drag = null;
    track.classList.remove('is-dragging');
  });
  track.addEventListener('click', event => {
    if (!dragged) return;
    event.preventDefault();
    event.stopPropagation();
    dragged = false;
  }, true);
  let frame;
  track.addEventListener('scroll', () => {
    cancelAnimationFrame(frame);
    frame = requestAnimationFrame(update);
  }, { passive: true });
  new ResizeObserver(update).observe(track);
  controls.hidden = false;
  update();
})();
