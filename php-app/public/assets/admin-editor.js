(() => {
  const form = document.querySelector('form.editor');
  if (!form) return;
  let unsaved = false, submitting = false;
  form.addEventListener('input',()=>{unsaved=true;});
  form.addEventListener('change',()=>{unsaved=true;});
  form.addEventListener('submit',()=>{submitting=true;});
  window.addEventListener('beforeunload',e=>{if(unsaved&&!submitting){e.preventDefault();e.returnValue='';}});
  const csrf = form.querySelector('[name=csrf]').value;
  const source = form.querySelector('[name=Tekst]');
  const visual = form.querySelector('.visual-editor');
  const canvas = visual?.querySelector('.editor-canvas');
  let dirty = false, sourceMode = false, savedRange = null;
  function sanitize(html) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const allowed = new Set(['P','BR','DIV','SPAN','STRONG','B','EM','I','U','H2','H3','H4','UL','OL','LI','BLOCKQUOTE','A','IMG','TABLE','TBODY','TR','TD','TH']);
    for (const el of [...doc.body.querySelectorAll('*')]) {
      if (['SCRIPT','STYLE','IFRAME','OBJECT','EMBED','FORM','INPUT','BUTTON','SVG','MATH'].includes(el.tagName)) { el.remove(); continue; }
      if (!allowed.has(el.tagName)) { el.replaceWith(...el.childNodes); continue; }
      for (const a of [...el.attributes]) {
        if (!['href','src','alt','title','colspan','rowspan'].includes(a.name)) el.removeAttribute(a.name);
        else if (['href','src'].includes(a.name)) {
          try {const u=new URL(a.value,location.origin); if (!['http:','https:','mailto:','tel:'].includes(u.protocol)) el.removeAttribute(a.name);} catch {el.removeAttribute(a.name);}
        }
      }
    }
    return doc.body.innerHTML;
  }
  if (visual && document.queryCommandSupported?.('bold')) {
    visual.hidden = false; source.hidden = true; source.required = false;
    canvas.addEventListener('input', () => {dirty=true;});
    canvas.addEventListener('keyup', remember); canvas.addEventListener('mouseup', remember);
    function remember(){const s=getSelection();if(s.rangeCount && canvas.contains(s.anchorNode))savedRange=s.getRangeAt(0).cloneRange();}
    canvas.addEventListener('paste', e => {e.preventDefault();document.execCommand('insertText',false,e.clipboardData.getData('text/plain'));dirty=true;});
    canvas.addEventListener('drop',e=>e.preventDefault());
    visual.querySelectorAll('[data-command]').forEach(b => {
      b.addEventListener('mousedown',e=>e.preventDefault());
      b.addEventListener('click',()=>{canvas.focus();document.execCommand(b.dataset.command,false,b.dataset.value||null);dirty=true;});
    });
    visual.querySelector('[data-editor-link]').addEventListener('click',()=>{
      const url=window.prompt('Poveznica (https://… ili /putanja)');if(!url)return;
      let parsed;try{parsed=new URL(url,location.origin);}catch{return;}
      if(!['http:','https:','mailto:','tel:'].includes(parsed.protocol))return;
      canvas.focus();if(savedRange){const s=getSelection();s.removeAllRanges();s.addRange(savedRange);}
      document.execCommand('createLink',false,url);dirty=true;
    });
    visual.querySelector('[data-source-toggle]').addEventListener('click',e=>{
      if(!sourceMode){if(dirty)source.value=canvas.innerHTML;source.hidden=false;canvas.hidden=true;e.target.textContent='Vizualni prikaz';}
      else{canvas.innerHTML=sanitize(source.value);source.hidden=true;canvas.hidden=false;dirty=true;e.target.textContent='HTML';}
      sourceMode=!sourceMode;
    });
    form.addEventListener('submit',()=>{if(!sourceMode&&dirty)source.value=canvas.innerHTML;});
  }
  function insert(file) {
    unsaved = true;
    const isImage=file.mime.startsWith('image/');
    const el=document.createElement(isImage?'img':'a');
    if(isImage){el.src=file.url;el.alt=file.name;}else{el.href=file.url;el.textContent=file.name;}
    const p=document.createElement('p');p.append(el);
    if(canvas&&!sourceMode&&!visual.hidden){canvas.append(p);dirty=true;canvas.dispatchEvent(new Event('input'));}
    else if(source){source.value+='\n'+p.outerHTML;}
    else form.querySelector('[name=Putanja]').value=file.url;
  }
  async function upload(file) {
    const data=new FormData();data.append('csrf',csrf);data.append('file',file);
    const response=await fetch('/admin?module=upload',{method:'POST',body:data});
    const result=await response.json();if(!response.ok)throw new Error(result.error||'Prijenos nije uspio.');return result;
  }
  form.querySelectorAll('[data-editor-upload],[data-agreement-upload]').forEach(input=>input.addEventListener('change',async()=>{
    const status=input.closest('.editor-attachments,.agreement-upload').querySelector('[data-upload-status]');
    input.disabled=true;
    // Prevent saving while a selected attachment is still uploading.
    const saves=[...form.querySelectorAll('.save button')];saves.forEach(b=>b.disabled=true);
    try{for(const file of input.files){status.textContent='Prijenos: '+file.name;insert(await upload(file));}status.textContent='Datoteke su dodane. Spremite nacrt ili objavite promjene.';}
    catch(e){status.textContent=e.message;}
    finally{input.disabled=false;input.value='';saves.forEach(b=>b.disabled=false);}
  }));
  visual?.querySelector('[data-library]').addEventListener('click',async()=>{
    const box=visual.querySelector('.editor-library');box.hidden=!box.hidden;if(box.hidden)return;
    box.textContent='Učitavanje…';
    try{
      const r=await fetch('/admin?module=media-list');if(!r.ok)throw new Error();const files=await r.json();box.textContent='';
      files.forEach(f=>{const b=document.createElement('button');b.type='button';b.textContent=f.OriginalName;b.addEventListener('click',()=>{insert({url:'/media/'+f.Filename,name:f.OriginalName,mime:f.Mime});box.hidden=true;});box.append(b);});
      if(!files.length)box.textContent='Nema prenesenih datoteka.';
    }catch{box.textContent='Biblioteku nije moguće učitati. Osvježite stranicu i provjerite prijavu.';}
  });
})();
