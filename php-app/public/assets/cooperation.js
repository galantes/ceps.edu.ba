(() => {
  const english = document.documentElement.lang === 'en';
  // The German source has its own section structure, so retain its original headings.
  if (document.documentElement.lang === 'de') return;
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
  const labels = english ? ['Overview', 'Projects and conferences', 'Calls for applications', 'Activities', 'Useful links', 'Contact', 'Business partners', 'Higher education partners', 'Agreements and contracts'] : ['Pregled', 'Projekti i konferencije', 'Konkursi', 'Aktivnosti', 'Korisni linkovi', 'Kontakt', 'Partneri iz privrede', 'Visokoškolski partneri', 'Ugovori i sporazumi'];
  const icons = ['world', 'briefcase', 'clipboard-list', 'calendar-event', 'link', 'mail', 'building-factory-2', 'school', 'file-certificate'];
  const controls = document.createElement('div');
  controls.className = 'cooperation-tools';
  controls.innerHTML = english ? '<label for="cooperation-search">Search all sections</label><div class="cooperation-search-row"><i class="ti ti-search" aria-hidden="true"></i><input id="cooperation-search" type="search" placeholder="Partner, call, country…"><button type="button">Clear</button></div><p class="cooperation-status" role="status"></p>' : '<label for="cooperation-search">Pretražite sve rubrike</label><div class="cooperation-search-row"><i class="ti ti-search" aria-hidden="true"></i><input id="cooperation-search" type="search" placeholder="Naziv partnera, konkurs, država…"><button type="button">Očisti</button></div><p class="cooperation-status" role="status"></p>';
  const input = controls.querySelector('input');
  const status = controls.querySelector('[role="status"]');
  const tabs = document.createElement('div');
  tabs.className = 'cooperation-tabs';
  tabs.setAttribute('role', 'tablist');
  tabs.setAttribute('aria-label', english ? 'International relations sections' : 'Rubrike međunarodne saradnje');
  const desktop = window.matchMedia('(min-width: 951px)');
  const orient = () => tabs.setAttribute('aria-orientation', desktop.matches ? 'vertical' : 'horizontal');
  orient(); desktop.addEventListener('change', orient);
  let selected = 0;
  const normalize = value => value.toLocaleLowerCase('bs').normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/đ/g, 'd');
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
  const results = document.createElement('section');
  results.className = 'cooperation-search-results';
  results.setAttribute('aria-label', english ? 'Search results' : 'Rezultati pretrage');
  results.hidden = true;
  const entries = panels.flatMap((panel, index) => {
    const rows = [...panel.querySelectorAll('.programme-news>li,.agreement-links>li,.cooperation-agreement-card,.cooperation-resource-list>li')];
    const insideRow = node => rows.some(row => row.contains(node));
    const items = rows.map(row => {
      const link = row.querySelector('.cooperation-news-title a') || row.querySelector('a:not([aria-hidden="true"])');
      const name = row.querySelector('.agreement-name');
      return { link, title: (link || name || row).textContent.trim(), text: row.textContent.trim() };
    });
    panel.querySelectorAll('a[href]').forEach(link => {
      if (!insideRow(link) && link.textContent.trim()) items.push({ link, title: link.textContent.trim(), text: link.textContent.trim() });
    });
    panel.querySelectorAll('p,li').forEach(node => {
      if (insideRow(node) || rows.some(row => node.contains(row))) return;
      const text = node.textContent.trim();
      if (!text) return;
      items.push({ title: '', text, linkedText: [...node.querySelectorAll('a')].map(link => normalize(link.textContent)) });
    });
    const seen = new Set();
    return items.filter(item => {
      const identity = (item.link?.getAttribute('href') || '') + '|' + normalize(item.text);
      if (seen.has(identity)) return false;
      seen.add(identity); return true;
    }).map(item => ({ ...item, index, searchable: normalize(item.text) }));
  });
  function excerpt(text, query) {
    const clean = text.replace(/\s+/g, ' ').trim();
    if (clean.length <= 260) return clean;
    const at = normalize(clean).indexOf(query);
    const start = Math.max(0, at - 70);
    return (start ? '…' : '') + clean.slice(start, start + 260) + (start + 260 < clean.length ? '…' : '');
  }
  function update() {
    const query = normalize(input.value.trim());
    panels.forEach((panel, i) => {
      panel.hidden = !!query || i !== selected;
      buttons[i].setAttribute('aria-selected', String(!query && i === selected));
      buttons[i].tabIndex = i === selected ? 0 : -1;
    });
    results.replaceChildren();
    results.hidden = !query;
    status.hidden = !query;
    if (!query) { status.textContent = ''; return; }
    const matches = entries.filter(item => item.searchable.includes(query) && !item.linkedText?.some(text => text.includes(query)));
    const heading = document.createElement('h2');
    heading.textContent = english ? 'Search results' : 'Rezultati pretrage';
    results.append(heading);
    const groups = new Map();
    matches.forEach(item => {
      let list = groups.get(item.index);
      if (!list) {
        const group = document.createElement('section');
        const title = document.createElement('h3');
        title.textContent = labels[item.index] || headings[item.index].textContent.trim();
        list = document.createElement('ul');
        list.className = 'cooperation-search-list';
        group.append(title, list); results.append(group); groups.set(item.index, list);
      }
      const row = document.createElement('li');
      if (item.link) {
        const link = document.createElement('a');
        ['href', 'target', 'rel'].forEach(attribute => {
          if (item.link.hasAttribute(attribute)) link.setAttribute(attribute, item.link.getAttribute(attribute));
        });
        link.textContent = item.title.replace(/^[\s·•]+/, '');
        row.append(link);
        const date = item.link.closest('li')?.querySelector('time');
        if (date) row.append(date.cloneNode(true));
      } else {
        const text = document.createElement('p');
        text.textContent = excerpt(item.text, query); row.append(text);
      }
      list.append(row);
    });
    status.textContent = matches.length ? (english ? `Results: ${matches.length}.` : `Broj rezultata: ${matches.length}.`) : (english ? 'No results. Try another search term.' : 'Nema rezultata. Pokušajte drugi pojam.');
  }
  input.addEventListener('input', update);
  controls.querySelector('button').addEventListener('click', () => { input.value = ''; update(); input.focus(); });
  content.replaceChildren(controls, results, ...panels);
  content.before(tabs);
  page.classList.add('cooperation-ready');
  function followHash() {
    const index = panels.findIndex(panel => [...panel.querySelectorAll('[id]')].some(node => '#'+node.id === location.hash));
    if (index >= 0) { selected = index; input.value = ''; update(); }
  }
  update(); followHash(); window.addEventListener('hashchange', followHash);
})();

