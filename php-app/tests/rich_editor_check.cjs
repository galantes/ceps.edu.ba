const {chromium}=require('playwright');
const http=require('http'),fs=require('fs'),path=require('path'),assert=require('assert');
const root=path.resolve(__dirname,'../public');
let uploads=0;
const server=http.createServer((req,res)=>{
 const url=new URL(req.url,'http://localhost');
 if(url.pathname==='/admin' && req.method==='POST'){
  let data='';req.on('data',c=>data+=c);req.on('end',()=>{assert(data.includes('name="csrf"'));assert(data.includes('name="file"'));uploads++;res.setHeader('Content-Type','application/json');res.end(JSON.stringify({url:'/media/'+'a'.repeat(32)+'.png',name:'image.png',mime:'image/png'}));});return;
 }
 if(url.pathname.startsWith('/assets/')){
  const file=path.resolve(root,'.'+url.pathname);if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.statusCode=404;return res.end();}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':'text/css');return res.end(fs.readFileSync(file));
 }
 res.setHeader('Content-Security-Policy',"default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'; img-src 'self' data: blob: https:; connect-src 'self'");
 res.end('<!doctype html><html><head><link rel="stylesheet" href="/assets/vendor/jodit/jodit.min.css"><link rel="stylesheet" href="/assets/admin.css"><script src="/assets/vendor/jodit/jodit.min.js" defer></script><script src="/assets/admin-editor.js" defer></script></head><body><main><h1>Rich editor test</h1><form class="editor"><input type="hidden" name="csrf" value="test-token"><textarea id="news-html" name="Tekst"><p>Test</p></textarea><div class="editor-attachments"><input type="file" data-editor-upload multiple><button type="button" data-library>Biblioteka</button><div class="editor-library" hidden></div><p data-upload-status role="status"></p></div><div class="save"><button name="action" value="draft">Spremi nacrt</button></div></form></main></body></html>');
});
(async()=>{
 await new Promise(r=>server.listen(0,'127.0.0.1',r));
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_PATH||undefined});
 try{
  const page=await browser.newPage({viewport:{width:1200,height:950}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:'+server.address().port);
  await page.locator('.jodit-wysiwyg').waitFor();
  await page.evaluate(()=>{
   const el=document.querySelector('.jodit-wysiwyg');el.focus();
   const dt=new DataTransfer();dt.setData('text/html','<p style="text-align:right;font-weight:700" onclick="alert(1)">Word tekst</p><table><tr><td>Količina</td><td>5</td></tr></table>');
   el.dispatchEvent(new ClipboardEvent('paste',{clipboardData:dt,bubbles:true,cancelable:true}));
  });
  await page.waitForFunction(()=>document.querySelector('.jodit-wysiwyg').textContent.includes('Word tekst'));
  assert(await page.locator('.jodit-wysiwyg table').count());
  assert(await page.locator('.jodit-wysiwyg [style*="text-align: right"],.jodit-wysiwyg [style*="text-align:right"]').count());
  assert.equal(await page.locator('.jodit-wysiwyg [onclick]').count(),0);
  await page.evaluate(()=>{
   const bytes=Uint8Array.from(atob('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aPioAAAAASUVORK5CYII='),c=>c.charCodeAt(0));
   const dt=new DataTransfer();dt.items.add(new File([bytes],'paste.png',{type:'image/png'}));
   document.querySelector('.jodit-wysiwyg').dispatchEvent(new ClipboardEvent('paste',{clipboardData:dt,bubbles:true,cancelable:true}));
  });
  await page.waitForFunction(()=>document.querySelector('.jodit-wysiwyg img[src^="/media/"]'));
  assert.equal(uploads,1);
  await page.evaluate(()=>document.querySelector('form').addEventListener('submit',e=>e.preventDefault()));
  await page.getByRole('button',{name:'Spremi nacrt',exact:true}).click();
  const value=await page.locator('[name=Tekst]').inputValue();assert(value.includes('/media/'));assert(value.includes('text-align:right'));assert(!value.includes('onclick'));
  assert.deepEqual(errors,[]);
  await page.screenshot({path:path.resolve(__dirname,'../rich-editor-preview.png')});
  console.log('PASS: editor initializes under CSP; formatted HTML/table paste; image clipboard upload; submit synchronization; no browser errors.');
 }finally{await browser.close();server.close();}
})().catch(e=>{console.error(e);server.close();process.exitCode=1});
