const {chromium}=require('playwright');
const fs=require('fs'),assert=require('assert');
const origin='http://127.0.0.1:8086';
(async()=>{
 const fixture=JSON.parse(fs.readFileSync('migration/output/german-fixture.json','utf8'));
 let httpChecks=0;
 for(const path of [...fixture.pages,...fixture.news]){
  const response=await fetch(origin+path);const html=await response.text();
  assert.equal(response.status,200,path);assert.equal(new URL(response.url).pathname,path,path+' redirected');
  assert(html.includes('<html lang="de">'),path+' language');
  assert(html.includes('property="og:locale" content="de_DE"'),path+' locale');httpChecks++;
 }
 const sitemap=await (await fetch(origin+'/sitemap.xml')).text();
 for(const path of [...fixture.pages.filter(p=>p!=='/de/suche'),...fixture.news])assert(sitemap.includes('<loc>https://ceps.edu.ba'+path+'</loc>'),path+' missing from sitemap');
 const files=JSON.parse(fs.readFileSync('php-app/content/de/files.json'));
 for(const [path,info] of Object.entries(files)){
  const response=await fetch(origin+'/'+path);assert.equal(response.status,200,path);
  const bytes=Buffer.from(await response.arrayBuffer());
  assert.equal(require('crypto').createHash('sha256').update(bytes).digest('hex'),info.sha256,path+' source PDF changed');
 }
 const legacy=await new Promise((resolve,reject)=>require('http').get(origin+'/StudijIT.aspx',{headers:{Host:'de.ceps.edu.ba'}},response=>{response.resume();resolve(response);}).on('error',reject));
 assert.equal(legacy.statusCode,301);assert.equal(legacy.headers.location,'https://ceps.edu.ba/de/studiengaenge/informationstechnologie');
 const browser=await chromium.launch({headless:true,channel:'msedge'});const results=[];
 try{
  for(const width of [1440,390]){
   const page=await browser.newPage({viewport:{width,height:1000},ignoreHTTPSErrors:true});const errors=[];
   page.on('pageerror',e=>errors.push(e.message));
   for(const path of ['/de','/de/ueber-uns','/de/studiengaenge/informationstechnologie','/de/internationale-beziehungen','/de/nachrichten','/de/kontakt',fixture.news.at(-1)]){
    const response=await page.goto(origin+path,{waitUntil:'domcontentloaded'});assert.equal(response.status(),200,path);
    await page.waitForTimeout(600);
    const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);assert(!overflow,`Overflow ${width} ${path}`);
    assert.equal(await page.locator('.utility-languages a[lang=bs]').count(),1);assert.equal(await page.locator('.utility-languages a[lang=en]').count(),1);
    if(path==='/de'){
     assert.equal(await page.locator('.hero-slide').count(),7);
     assert.equal(await page.locator('.study-card').count(),7);
     await page.locator('[data-next]').click();
     await page.waitForFunction(()=>document.querySelectorAll('.hero-slide:not([hidden])').length===1);
     assert.equal(await page.locator('.hero-slide:not([hidden])').count(),1);
     for(const link of await page.locator('.latest-card h3 a').evaluateAll(nodes=>nodes.map(n=>new URL(n.href).pathname)))assert(fixture.news.includes(link),link+' non-German news');
    }
    if(path.includes('/studiengaenge/')){
     assert(await page.locator('.study-title>span').evaluate(span=>span.getBoundingClientRect().right<=span.closest('h1').getBoundingClientRect().right+1),'Clipped German study title');
     const tabs=page.getByRole('tab');assert((await tabs.count())>=3,'Missing German study tabs');
     await page.getByRole('tab',{name:'Dauer und Abschluss',exact:true}).click();
     assert((await page.locator('[role=tabpanel]:visible').innerText()).includes('180 ECTS'));
    }
    if(path==='/de/kontakt')assert.equal(await page.locator('main form,main input,main textarea').count(),0);
    await page.screenshot({path:`migration/output/de-${width}-${path.slice(1).replaceAll('/','_')}.png`,fullPage:path==='/de'});
    results.push({path,width,overflow});
   }
   assert.deepEqual(errors,[]);await page.close();
  }
  const page=await browser.newPage();await page.goto(origin+'/de/suche?q=Abkommen');
  assert((await page.locator('.search-summary').innerText()).includes('Beiträge.'));
  await page.close();
 }finally{await browser.close();}
 const report={status:'passed',httpChecks,sourcePdfs:Object.keys(files).length,browserChecks:results.length,pages:results};
 fs.writeFileSync('migration/output/german-browser-check.json',JSON.stringify(report,null,2));console.log(JSON.stringify(report));
})().catch(e=>{console.error(e.stack);process.exit(1)});
