// A failed external image also gets the shared CEPS fallback, only once.
document.addEventListener('error', (event) => {
  const image = event.target;
  if (!(image instanceof HTMLImageElement) || !image.matches('.news-cover')) return;
  const fallback = image.dataset.fallback;
  if (fallback && image.src !== fallback) image.src = fallback;
}, true);

// Fill only the existing space beneath shorter homepage news titles.
window.addEventListener('DOMContentLoaded', () => {
  const copies = [...document.querySelectorAll('.latest-copy')];
  if (!copies.length) return;
  function fitExcerpts() {
    copies.forEach(copy => {
      const excerpt = copy.querySelector('.latest-excerpt');
      if (!excerpt) return;
      excerpt.hidden = true;
      const title = copy.querySelector('h3');
      const link = copy.querySelector(':scope > .text-link');
      const top = title.offsetTop + title.offsetHeight + 10;
      const lines = Math.min(3, Math.floor((link.offsetTop - top - 12) / 21));
      if (lines < 1 || !excerpt.textContent.trim()) return;
      excerpt.style.top = `${top}px`;
      excerpt.style.webkitLineClamp = String(lines);
      excerpt.hidden = false;
    });
  }
  const observer = new ResizeObserver(fitExcerpts);
  copies.forEach(copy => observer.observe(copy));
  document.fonts.ready.then(fitExcerpts);
  fitExcerpts();
});
