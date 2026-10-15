document.querySelectorAll('[data-study]').forEach(page => {
  const english = document.documentElement.lang === 'en';
  const german = document.documentElement.lang === 'de';
  const content = page.querySelector('.legacy-content');
  const headings = [...content.querySelectorAll('h2')];
  if (!headings.length) return;
  const groups = [
    [german ? 'Über den Studiengang' : (english ? 'About the programme' : 'O studiju'), 'school'], [german ? 'Fachrichtungen' : (english ? 'Study tracks' : 'Smjerovi'), 'route'], [german ? 'Dauer und Abschluss' : (english ? 'Duration and qualifications' : 'Trajanje i zvanje'), 'award'],
    [german ? 'Studienplan und Dokumente' : (english ? 'Curriculum and documents' : 'Nastavni plan i dokumenti'), 'files']
  ].map(([title, icon]) => ({ title, icon, sections: [] }));
  const normalize = text => text.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  headings.forEach((heading, index) => {
    const range = document.createRange();
    if (index === 0) range.setStart(content, 0); else range.setStartBefore(heading);
    if (headings[index + 1]) range.setEndBefore(headings[index + 1]); else range.setEnd(content, content.childNodes.length);
    const section = document.createElement('section');
    section.className = 'study-tab-section';
    section.append(range.cloneContents());
    const title = normalize(heading.textContent);
    if (/novosti|news|presse|^press$/i.test(title)) return;
    const category = index === 0 ? 0 : /nastavni|dokument|plan i program|curricul|document|learning outcome|studienplan|lehrplan/i.test(title) ? 3 : /trajanje|naziv|zvanje|duration|qualification|title|degree|studiendauer|abschluss/i.test(title) ? 2 : 1;
    groups[category].sections.push(section);
  });
  const tabs = document.createElement('div'); tabs.className = 'study-page-tabs';
  tabs.setAttribute('role', 'tablist'); tabs.setAttribute('aria-label', german ? 'Studieninformationen' : (english ? 'Programme information' : 'Informacije o studiju'));
  const desktop = window.matchMedia('(min-width: 951px)');
  const updateOrientation = () => tabs.setAttribute('aria-orientation', desktop.matches ? 'vertical' : 'horizontal');
  updateOrientation(); desktop.addEventListener('change', updateOrientation);
  const panels = [], buttons = [];
  groups.filter(group => group.sections.length).forEach((group, index) => {
    const button = document.createElement('button'); button.type = 'button';
    button.id = `study-tab-${index}`; button.setAttribute('role', 'tab');
    button.innerHTML = `<i class="ti ti-${group.icon}" aria-hidden="true"></i><span>${group.title}</span>`;
    const panel = document.createElement('div'); panel.id = `study-panel-${index}`;
    panel.className = 'study-page-panel'; panel.setAttribute('role', 'tabpanel');
    panel.setAttribute('aria-labelledby', button.id); panel.tabIndex = 0;
    button.setAttribute('aria-controls', panel.id);
    panel.append(...group.sections); panels.push(panel); buttons.push(button); tabs.append(button);
    button.addEventListener('click', () => {
      activate(index);
      if (desktop.matches) content.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
    });
    button.addEventListener('keydown', event => {
      const forward = desktop.matches ? 'ArrowDown' : 'ArrowRight';
      const backward = desktop.matches ? 'ArrowUp' : 'ArrowLeft';
      const keys = [forward, backward, 'Home', 'End'];
      if (!keys.includes(event.key)) return;
      event.preventDefault();
      const next = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (event.key === forward ? 1 : -1) + buttons.length) % buttons.length;
      activate(next); buttons[next].focus();
    });
  });
  function activate(index) {
    panels.forEach((panel, i) => { panel.hidden = i !== index; buttons[i].setAttribute('aria-selected', String(i === index)); buttons[i].tabIndex = i === index ? 0 : -1; });
  }
  content.replaceChildren(...panels);
  content.before(tabs); page.classList.add('study-tabs-ready');
  function followHash() {
    const index = panels.findIndex(panel => [...panel.querySelectorAll('[id]')].some(node => `#${node.id}` === location.hash));
    if (index >= 0) activate(index);
  }
  activate(0); followHash(); window.addEventListener('hashchange', followHash);
});
