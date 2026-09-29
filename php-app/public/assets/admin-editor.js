(() => {
  const form = document.querySelector('form.editor');
  if (!form) return;
  const csrf = form.querySelector('[name=csrf]').value;
  const source = form.querySelector('[name=Tekst]');
  const original = source?.value || '';
  const status = form.querySelector('[data-upload-status]');
  let editor, unsaved = false, submitting = false, richDirty = false, pending = 0;
  const mark = () => { unsaved = true; };
  form.addEventListener('input', mark); form.addEventListener('change', mark);
  window.addEventListener('beforeunload', e => { if (unsaved && !submitting) { e.preventDefault(); e.returnValue = ''; } });
  function sanitize(html) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const tags = new Set('P BR DIV SPAN STRONG B EM I U S H1 H2 H3 H4 H5 H6 UL OL LI BLOCKQUOTE A IMG TABLE THEAD TBODY TFOOT TR TD TH HR SUP SUB FIGURE FIGCAPTION'.split(' '));
    const rules = {
      'text-align': /^(left|center|right|justify)$/i, 'font-weight': /^(normal|bold|[1-9]00)$/i,
      'font-style': /^(normal|italic)$/i, 'text-decoration': /^(none|underline|line-through|underline line-through)$/i,
      'color': /^(#[a-f0-9]{3,8}|[a-z]{3,20}|rgba?\([0-9.,% ]+\))$/i,
      'background-color': /^(#[a-f0-9]{3,8}|[a-z]{3,20}|rgba?\([0-9.,% ]+\))$/i,
      'font-size': /^([1-9]|[1-6][0-9]|7[0-2])(px|pt)$/i,
      'width': /^(auto|[0-9]{1,4}(px|%))$/i, 'height': /^(auto|[0-9]{1,4}(px|%))$/i,
      'max-width': /^(auto|[0-9]{1,4}(px|%))$/i, 'margin-left': /^(auto|0|[0-9]{1,3}(px|pt))$/i,
      'margin-right': /^(auto|0|[0-9]{1,3}(px|pt))$/i, 'border-collapse': /^(collapse|separate)$/i
    };
    for (const el of [...doc.body.querySelectorAll('*')]) {
      if (['SCRIPT','STYLE','IFRAME','OBJECT','EMBED','FORM','INPUT','BUTTON','SVG','MATH','LINK','META'].includes(el.tagName)) { el.remove(); continue; }
      if (!tags.has(el.tagName)) { el.replaceWith(...el.childNodes); continue; }
      if (el.hasAttribute('align')) el.style.textAlign = el.getAttribute('align');
      for (const attr of [...el.attributes]) {
        if (attr.name === 'style') {
          const safe = [...el.style].filter(key => rules[key]?.test(el.style.getPropertyValue(key).trim())).map(key => key + ':' + el.style.getPropertyValue(key)).join(';');
          if (safe) el.setAttribute('style', safe); else el.removeAttribute('style');
        } else if (!['href','src','alt','title','colspan','rowspan'].includes(attr.name)) el.removeAttribute(attr.name);
        else if (['href','src'].includes(attr.name)) {
          try { const u = new URL(attr.value, location.origin); if (!['http:','https:','mailto:','tel:'].includes(u.protocol) && !(el.tagName === 'IMG' && /^data:image\/(png|jpeg|webp);base64,/i.test(attr.value))) el.removeAttribute(attr.name); }
          catch { el.removeAttribute(attr.name); }
        }
      }
    }
    return doc.body.innerHTML;
  }
  function busy(delta) {
    pending += delta;
    form.querySelectorAll('.save button').forEach(b => b.disabled = pending > 0);
  }
  async function upload(file) {
    const data = new FormData(); data.append('csrf', csrf); data.append('file', file);
    const response = await fetch('/admin?module=upload', {method:'POST', body:data});
    const result = await response.json();
    if (!response.ok) throw new Error(result.error || 'Prijenos nije uspio.');
    return result;
  }
  function insert(file) {
    mark();
    const image = file.mime.startsWith('image/');
    const el = document.createElement(image ? 'img' : 'a');
    if (image) { el.src = file.url; el.alt = file.name; } else { el.href = file.url; el.textContent = file.name; }
    if (editor) { editor.s.insertHTML(el.outerHTML); richDirty = true; }
    else if (source) source.value += '\n' + el.outerHTML;
    else form.querySelector('[name=Putanja]').value = file.url;
  }
  async function uploadFiles(files) {
    if (!files.length) return;
    mark(); busy(1); editor?.s.save();
    try {
      for (const file of files) {
        if (status) status.textContent = 'Prijenos: ' + file.name;
        const result = await upload(file);
        editor?.s.restore(); insert(result); editor?.s.save();
      }
      if (status) status.textContent = 'Datoteke su dodane. Spremite nacrt ili objavite promjene.';
    } catch(e) { if (status) status.textContent = e.message; }
    finally { editor?.s.restore(); busy(-1); }
  }
  if (source && window.Jodit) {
    source.value = sanitize(original);
    try {
      editor = Jodit.make(source, {
        height: 480, minHeight: 320, language: 'en', sourceEditor: 'area',
        toolbarAdaptive: false, toolbarSticky: false, showXPathInStatusbar: false,
        askBeforePasteHTML: false, askBeforePasteFromWord: false,
        disablePlugins: ['paste','paste-from-word','drag-and-drop','drag-and-drop-element','file','image'],
        buttons: ['undo','redo','|','bold','italic','underline','strikethrough','|','paragraph','fontsize','brush','|','ul','ol','|','align','outdent','indent','|','link','table','|','cepsImage','hr','eraser','|','source','fullsize'],
        controls: { cepsImage: {name:'Slika / PDF', tooltip:'Dodaj sliku ili PDF', icon:'image', exec: () => form.querySelector('[data-editor-upload]').click()} },
        cleanHTML: { denyTags: 'script,iframe,object,embed,form,input,button,svg,math', removeOnError: true },
        placeholder: 'Napišite sadržaj ili zalijepite tekst iz Worda…'
      });
      source.required = false;
      editor.events.on('change', () => { mark(); richDirty = true; });
      editor.editor.addEventListener('paste', async e => {
        const transfer = e.clipboardData;
        if (!transfer) return;
        e.preventDefault(); e.stopImmediatePropagation();
        const html = transfer.getData('text/html');
        const files = [...transfer.files];
        if (!html && files.length) { await uploadFiles(files); return; }
        if (!html) { editor.s.insertHTML(transfer.getData('text/plain').split(/\r?\n/).map(t => { const p=document.createElement('p');p.textContent=t;return p.outerHTML; }).join('')); return; }
        const doc = new DOMParser().parseFromString(sanitize(html), 'text/html');
        mark(); busy(1); editor.s.save();
        try {
          let missing = 0;
          for (const img of doc.querySelectorAll('img')) {
            const src = img.getAttribute('src') || '';
            if (src.startsWith('data:')) {
              const [head, body] = src.split(','); const mime = head.slice(5).split(';')[0];
              const bytes = Uint8Array.from(atob(body), c => c.charCodeAt(0));
              const result = await upload(new File([bytes], 'zalijepljena-slika.' + mime.split('/')[1], {type:mime}));
              img.src = result.url;
            } else if (!src) {
              if (files.length) img.src = (await upload(files.shift())).url;
              else { img.remove(); missing++; }
            }
          }
          editor.s.restore(); editor.s.insertHTML(doc.body.innerHTML); richDirty = true;
          if (status) status.textContent = missing ? 'Tekst je zalijepljen. Slike koje Word nije prenio dodajte zasebno putem gumba Slika / PDF.' : '';
        } catch(e) { if (status) status.textContent = 'Lijepljenje nije dovršeno: ' + e.message; }
        finally { busy(-1); }
      }, true);
      editor.editor.addEventListener('dragover', e => { if (e.dataTransfer?.types.includes('Files')) e.preventDefault(); });
      editor.editor.addEventListener('drop', e => { if (e.dataTransfer?.files.length) { e.preventDefault(); e.stopImmediatePropagation(); uploadFiles([...e.dataTransfer.files]); } }, true);
    } catch(e) { source.value = original; if (status) status.textContent = 'Editor nije učitan. Dostupan je HTML prikaz.'; }
  }
  form.addEventListener('submit', e => {
    if (pending) { e.preventDefault(); return; }
    if (editor) source.value = richDirty ? sanitize(editor.value) : original;
    if (source && !source.value.trim()) { e.preventDefault(); if (status) status.textContent = 'Unesite sadržaj novosti.'; editor?.s.focus(); return; }
    submitting = true;
  });
  form.querySelectorAll('[data-editor-upload],[data-agreement-upload]').forEach(input => input.addEventListener('change', async () => {
    const files = [...input.files]; input.disabled = true;
    await uploadFiles(files); input.disabled = false; input.value = '';
  }));
  form.querySelector('[data-library]')?.addEventListener('click', async () => {
    const box = form.querySelector('.editor-library'); box.hidden = !box.hidden; if (box.hidden) return;
    editor?.s.save(); box.textContent = 'Učitavanje…';
    try {
      const r = await fetch('/admin?module=media-list'); if (!r.ok) throw new Error();
      const files = await r.json(); box.textContent = '';
      for (const file of files) { const b=document.createElement('button'); b.type='button';b.textContent=file.OriginalName;b.addEventListener('click',()=>{editor?.s.restore();insert({url:'/media/'+file.Filename,name:file.OriginalName,mime:file.Mime});box.hidden=true;});box.append(b); }
      if (!files.length) box.textContent = 'Nema prenesenih datoteka.';
    } catch { box.textContent = 'Biblioteku nije moguće učitati. Osvježite stranicu i provjerite prijavu.'; }
  });
})();
