(() => {
  const popup = document.querySelector('.home-announcement');
  if (!popup) return;
  const image = popup.querySelector('img');
  let timer;
  const close = () => popup.close();
  popup.querySelector('button').addEventListener('click', close);
  popup.addEventListener('click', event => { if (event.target === popup) close(); });
  popup.addEventListener('close', () => clearTimeout(timer));
  const show = () => {
    if (!image.naturalWidth) return;
    popup.showModal();
    timer = setTimeout(close, 15000);
  };
  if (image.complete) show(); else image.addEventListener('load', show, { once: true });
})();
