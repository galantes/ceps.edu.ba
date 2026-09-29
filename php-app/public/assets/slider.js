document.querySelectorAll('[data-slider]').forEach(slider => {
  const slides = [...slider.querySelectorAll('.hero-slide')];
  const dots = [...slider.querySelectorAll('[data-slide]')];
  const motion = matchMedia('(prefers-reduced-motion: reduce)');
  let current = 0, paused = motion.matches, timer;
  let transition = [];
  const videos = slides.map(slide => slide.querySelector('video'));
  videos.forEach(video => {
    if (!video) return;
    video.addEventListener('playing', () => video.classList.add('is-playing'));
    video.addEventListener('error', () => video.classList.remove('is-playing'));
  });
  function syncVideos() {
    videos.forEach((video, i) => {
      if (!video) return;
      if (i !== current || paused || document.hidden || motion.matches) {
        video.pause();
        return;
      }
      if (!video.getAttribute('src')) video.src = video.dataset.src;
      video.muted = true;
      video.play().catch(() => video.classList.remove('is-playing'));
    });
  }
  function schedule() {
    clearInterval(timer);
    if (!paused && !document.hidden && !slider.matches(':hover') && !slider.contains(document.activeElement)) {
      timer = setInterval(() => show(current + 1), 7000);
    }
  }
  function show(index, animate = true) {
    const next = (index + slides.length) % slides.length;
    if (next === current) return;
    transition.forEach(animation => animation.cancel());
    transition = [];
    slides.forEach(slide => slide.classList.remove('slide-leaving'));
    const previous = slides[current];
    current = next;
    slides.forEach((slide, i) => {
      slide.hidden = i !== current;
      slide.inert = i !== current;
      slide.setAttribute('aria-hidden', String(i !== current));
    });
    if (animate && !motion.matches && !matchMedia('(max-width: 700px)').matches) {
      previous.hidden = false;
      previous.classList.add('slide-leaving');
      const options = { duration: 650, easing: 'ease-in-out' };
      const outgoing = previous.animate([{ opacity: 1 }, { opacity: 0 }], options);
      const incoming = slides[current].animate([{ opacity: 0 }, { opacity: 1 }], options);
      transition = [outgoing, incoming];
      outgoing.onfinish = () => {
        previous.hidden = true;
        previous.classList.remove('slide-leaving');
        transition = [];
      };
    }
    dots.forEach((dot, i) => { if (i === current) dot.setAttribute('aria-current', 'true'); else dot.removeAttribute('aria-current'); });
    syncVideos();
  }
  function updatePause() {
    schedule();
    syncVideos();
  }
  slider.querySelector('.slider-controls').hidden = false;
  slider.querySelector('[data-prev]').addEventListener('click', () => { show(current - 1); schedule(); });
  slider.querySelector('[data-next]').addEventListener('click', () => { show(current + 1); schedule(); });
  dots.forEach((dot, i) => dot.addEventListener('click', () => { show(i); schedule(); }));
  slider.addEventListener('mouseenter', schedule);
  slider.addEventListener('mouseleave', schedule);
  slider.addEventListener('focusin', schedule);
  slider.addEventListener('focusout', () => setTimeout(schedule, 0));
  document.addEventListener('visibilitychange', () => { schedule(); syncVideos(); });
  motion.addEventListener('change', () => { paused = motion.matches; updatePause(); });
  show(Math.floor(Math.random() * slides.length), false);
  updatePause();
});
