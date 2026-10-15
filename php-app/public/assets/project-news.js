(() => {
  const section = document.querySelector('#project-news');
  if (!section) return;
  const languages = [...section.querySelectorAll(':scope > .project-news-language')];
  if (!languages.length) return;
  const tabs = document.createElement('div');
  tabs.className = 'project-language-tabs';
  tabs.setAttribute('role', 'tablist');
  tabs.setAttribute('aria-label', document.documentElement.lang === 'en' ? 'Post language' : 'Jezik objava');
  languages[0].before(tabs);
  const buttons = [], panels = [];
  languages.forEach((language, index) => {
    const summary = language.querySelector(':scope > summary');
    const button = document.createElement('button');
    button.type = 'button';
    button.id = `project-language-tab-${index}`;
    button.setAttribute('role', 'tab');
    button.setAttribute('aria-label', summary.getAttribute('aria-label'));
    button.append(summary.querySelector('img'), summary.querySelector('.project-news-count'));
    button.querySelector('img').alt = '';
    const panel = document.createElement('div');
    panel.id = `project-language-panel-${index}`;
    panel.className = 'project-language-panel';
    panel.lang = language.lang;
    panel.setAttribute('role', 'tabpanel');
    panel.setAttribute('aria-labelledby', button.id);
    panel.tabIndex = 0;
    button.setAttribute('aria-controls', panel.id);
    summary.remove();
    while (language.firstChild) panel.append(language.firstChild);
    language.replaceWith(panel);
    tabs.append(button);
    buttons.push(button); panels.push(panel);
    button.addEventListener('click', () => select(index));
    button.addEventListener('keydown', event => {
      let next;
      if (event.key === 'ArrowRight') next = (index + 1) % buttons.length;
      if (event.key === 'ArrowLeft') next = (index - 1 + buttons.length) % buttons.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = buttons.length - 1;
      if (next === undefined) return;
      event.preventDefault(); select(next); buttons[next].focus();
    });
  });
  function select(index) {
    buttons.forEach((button, i) => {
      button.setAttribute('aria-selected', String(i === index));
      button.tabIndex = i === index ? 0 : -1;
      panels[i].hidden = i !== index;
    });
  }
  select(0);
})();
