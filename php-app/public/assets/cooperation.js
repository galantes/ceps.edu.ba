(() => {
  const page = document.querySelector('[data-cooperation]');
  if (!page) return;
  const content = page.querySelector('.legacy-content');
  const headings = [...content.querySelectorAll('h2')];
  if (!headings.length) return;
  const panels = headings.map((heading, i) => {
    const range = document.createRange();
    range.setStartBefore(heading);
    if (headings[i + 1]) range.setEndBefore(headings[i + 1]);
    else range.setEnd(content, content.childNodes.length);
    const panel = document.createElement('section');
    panel.className = 'cooperation-panel';
    panel.id = `cooperation-panel-${i}`;
    panel.setAttribute('role', 'tabpanel');
    panel.setAttribute('aria-labelledby', `cooperation-tab-${i}`);
    panel.append(range.cloneContents());
    return panel;
  });
  const labels = ['Pregled', 'Projekti i konferencije', 'Natječaji', 'Aktivnosti', 'Korisne poveznice', 'Kontakt', 'Partneri iz privrede', 'Visokoškolski partneri', 'Ugovori i sporazumi'];
  const icons = ['world', 'briefcase', 'clipboard-list', 'calendar-event', 'link', 'mail', 'building-factory-2', 'school', 'file-certificate'];
  const controls = document.createElement('div');
  controls.className = 'cooperation-tools';
  controls.innerHTML = '<label for="cooperation-search">Pretražite sve rubrike</label><div class="cooperation-search-row"><i class="ti ti-search" aria-hidden="true"></i><input id="cooperation-search" type="search" placeholder="Naziv partnera, natječaj, država…"><button type="button">Očisti</button></div><p class="cooperation-status" role="status"></p>';
  const input = controls.querySelector('input');
  const status = controls.querySelector('[role="status"]');
  const tabs = document.createElement('div');
  tabs.className = 'cooperation-tabs';
  tabs.setAttribute('role', 'tablist');
  tabs.setAttribute('aria-label', 'Rubrike međunarodne suradnje');
  const desktop = window.matchMedia('(min-width: 951px)');
  const orient = () => tabs.setAttribute('aria-orientation', desktop.matches ? 'vertical' : 'horizontal');
  orient(); desktop.addEventListener('change', orient);
  let selected = 0;
  const normalize = value => value.toLocaleLowerCase('hr').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd');
  const buttons = headings.map((heading, i) => {
    const button = document.createElement('button');
    button.type = 'button'; button.id = `cooperation-tab-${i}`;
    const glyph = document.createElement('i');
    glyph.className = `ti ti-${icons[i] || 'file-text'}`;
    glyph.setAttribute('aria-hidden', 'true');
    const label = document.createElement('span');
    label.textContent = labels[i] || heading.textContent.trim();
    button.append(glyph, label);
    button.setAttribute('role', 'tab'); button.setAttribute('aria-controls', panels[i].id);
    button.addEventListener('click', () => {
      input.value = ''; selected = i; update();
      if (desktop.matches) content.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
    });
    button.addEventListener('keydown', event => {
      let next = i;
      if (event.key === (desktop.matches ? 'ArrowDown' : 'ArrowRight')) next = (i + 1) % headings.length;
      else if (event.key === (desktop.matches ? 'ArrowUp' : 'ArrowLeft')) next = (i + headings.length - 1) % headings.length;
      else if (event.key === 'Home') next = 0;
      else if (event.key === 'End') next = headings.length - 1;
      else return;
      event.preventDefault(); buttons[next].click(); buttons[next].focus();
    });
    tabs.append(button); return button;
  });
  function update() {
    const query = normalize(input.value.trim());
    let found = 0;
    panels.forEach((panel, i) => {
      const matches = !query || normalize(panel.textContent).includes(query);
      panel.hidden = query ? !matches : i !== selected;
      if (query && matches) found++;
      panel.querySelectorAll('.programme-news>li,.agreement-links>li,.cooperation-agreement-card').forEach(row => { row.hidden = !!query && !normalize(row.textContent).includes(query); });
      panel.querySelectorAll('.older-posts').forEach(detail => { detail.open = !!query; });
      buttons[i].setAttribute('aria-selected', String(!query && i === selected));
      buttons[i].tabIndex = i === selected ? 0 : -1;
    });
    status.textContent = query ? (found ? `Broj pronađenih rubrika: ${found}.` : 'Nema rezultata. Pokušajte drugi pojam.') : '';
    status.hidden = !query;
  }
  input.addEventListener('input', update);
  controls.querySelector('button').addEventListener('click', () => { input.value = ''; update(); input.focus(); });
  content.replaceChildren(controls, ...panels);
  content.before(tabs);
  page.classList.add('cooperation-ready');
  function followHash() {
    const index = panels.findIndex(panel => [...panel.querySelectorAll('[id]')].some(node => '#'+node.id === location.hash));
    if (index >= 0) { selected = index; input.value = ''; update(); }
  }
  update(); followHash(); window.addEventListener('hashchange', followHash);
})();

