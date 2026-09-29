const panel = document.querySelector('.contact-panel');
const launcher = document.querySelector('.contact-launcher');
const closeContact = document.querySelector('.contact-close');
if (panel && launcher && closeContact) {
  function setContactOpen(open, focus = false) {
    panel.hidden = !open;
    launcher.hidden = open;
    launcher.setAttribute('aria-expanded', String(open));
    if (focus) (open ? closeContact : launcher).focus({ preventScroll: true });
  }
  setContactOpen(false);
  closeContact.addEventListener('click', () => setContactOpen(false, true));
  launcher.addEventListener('click', () => setContactOpen(true, true));
  panel.addEventListener('keydown', event => {
    if (event.key === 'Escape') setContactOpen(false, true);
  });
}
