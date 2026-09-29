(() => {
  const groups = [...document.querySelectorAll('.school-header .nav-group')];
  groups.forEach(group => {
    let timer;
    const cancel = () => clearTimeout(timer);
    const closeSoon = () => {
      cancel();
      timer = setTimeout(() => { group.open = false; }, 450);
    };
    group.addEventListener('toggle', () => {
      cancel();
      if (group.open) groups.forEach(other => { if (other !== group) other.open = false; });
    });
    group.addEventListener('pointerenter', event => {
      cancel();
      if (event.pointerType === 'mouse' && group.closest('.desktop-navigation') && matchMedia('(hover: hover) and (pointer: fine)').matches) {
        groups.forEach(other => { if (other !== group) other.open = false; });
        group.open = true;
      }
    });
    group.addEventListener('pointerleave', event => {
      if (event.pointerType === 'mouse') closeSoon();
    });
    group.addEventListener('focusin', cancel);
    group.addEventListener('focusout', event => {
      if (!group.contains(event.relatedTarget)) closeSoon();
    });
    group.addEventListener('keydown', event => {
      if (event.key === 'Escape') {
        group.open = false;
        group.querySelector('summary').focus();
      }
    });
  });
  document.addEventListener('click', event => {
    groups.forEach(group => { if (!group.contains(event.target)) group.open = false; });
  });
})();

// Collapse long page indexes into an explicit section picker.
document.querySelectorAll('.related-toc').forEach((toc, index) => {
  const links = [...toc.querySelectorAll('nav a')];
  if (links.length <= 6) return;
  const form = document.createElement('form');
  form.className = 'section-picker';
  const label = document.createElement('label');
  label.htmlFor = `section-picker-${index}`;
  label.textContent = 'Idi na odjeljak';
  const select = document.createElement('select');
  select.id = label.htmlFor;
  links.forEach(link => {
    const option = document.createElement('option');
    option.value = link.getAttribute('href');
    option.textContent = link.textContent.trim();
    select.append(option);
  });
  const button = document.createElement('button');
  button.type = 'submit';
  button.textContent = 'Prikaži';
  form.append(label, select, button);
  form.addEventListener('submit', event => {
    event.preventDefault();
    const target = document.getElementById(select.value.slice(1));
    if (!target) return;
    location.hash = select.value;
    target.tabIndex = -1;
    target.focus({preventScroll:true});
  });
  toc.replaceWith(form);
});

// Keep only the navigation visible once the identity header has scrolled away.
(() => {
  const header = document.querySelector('.school-header');
  const navigation = header?.querySelector('.navigation-wrap');
  if (!navigation) return;
  const updateOffset = () => {
    header.style.setProperty('--header-scroll-away', `${navigation.offsetTop}px`);
  };
  const observer = new ResizeObserver(updateOffset);
  observer.observe(header);
  observer.observe(navigation);
  updateOffset();
})();
