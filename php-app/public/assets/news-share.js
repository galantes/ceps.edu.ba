document.querySelectorAll('.news-share').forEach(section => {
  const copy = section.querySelector('[data-copy-link]');
  const print = section.querySelector('[data-print-news]');
  const status = section.querySelector('.news-share-status');
  copy.hidden = false;
  print.hidden = false;
  print.addEventListener('click', () => window.print());
  copy.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(section.dataset.shareUrl);
      status.textContent = 'Link je kopiran.';
      window.setTimeout(() => { status.textContent = ''; }, 4000);
    } catch {
      status.textContent = '';
      const label = document.createElement('label');
      label.textContent = 'Kopirajte link: ';
      const input = document.createElement('input');
      input.type = 'text';
      input.readOnly = true;
      input.value = section.dataset.shareUrl;
      label.append(input);
      status.append(label);
      input.focus();
      input.select();
    }
  });
});
