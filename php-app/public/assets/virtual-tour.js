document.querySelectorAll('.virtual-tour').forEach(tour => {
  const button = tour.querySelector('.tour-fullscreen');
  function update() {
    const expanded = document.fullscreenElement === tour || tour.classList.contains('tour-expanded');
    button.querySelector('span').textContent = expanded ? (document.documentElement.lang === 'en' ? 'Exit full screen' : 'Zatvori cijeli ekran') : (document.documentElement.lang === 'en' ? 'Full screen' : 'Cijeli ekran');
    button.querySelector('i').className = `ti ti-${expanded ? 'minimize' : 'maximize'}`;
    button.setAttribute('aria-pressed', String(expanded));
  }
  button.addEventListener('click', async () => {
    if (document.fullscreenElement === tour) await document.exitFullscreen();
    else if (tour.classList.contains('tour-expanded')) {
      tour.classList.remove('tour-expanded'); document.body.classList.remove('tour-lock');
    } else {
      try {
        if (!tour.requestFullscreen) throw new Error('Fullscreen unavailable');
        await tour.requestFullscreen();
      } catch {
        tour.classList.add('tour-expanded'); document.body.classList.add('tour-lock');
      }
    }
    update();
  });
  document.addEventListener('fullscreenchange', update);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && tour.classList.contains('tour-expanded')) {
      tour.classList.remove('tour-expanded'); document.body.classList.remove('tour-lock'); update(); button.focus();
    }
  });
  update();
});
