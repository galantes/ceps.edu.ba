const { chromium } = require('playwright');
const fs=require('fs'),assert=require('assert');
(async()=>{
 const browser=await chromium.launch({headless:true,channel:'msedge'});
 const result=[];
 try {
  for(const viewport of [{width:1440,height:1000},{width:390,height:844}]){
   const page=await browser.newPage({viewport,ignoreHTTPSErrors:true});const errors=[];page.on('pageerror',e=>errors.push(e.message));
   for(const path of ['/en','/kontakt','/en/contact','/en/studies/information-technology','/en/international-relations','/en/projects/impact-campus-knowledge-valorization']){
    const response=await page.goto('http://127.0.0.1:8086'+path,{waitUntil:'domcontentloaded'});assert.equal(response.status(),200,path);await page.waitForTimeout(750);
    const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);assert(!overflow,`Overflow ${viewport.width} ${path}`);
    if(/contact|kontakt/.test(path))assert.equal(await page.locator('main form,main textarea,main input').count(),0);
    if(path.includes('/studies/')){assert(await page.getByRole('tab',{name:'About the programme'}).count());const last=page.getByRole('tab').last();await last.click();assert.equal(await last.getAttribute('aria-selected'),'true');}
    if(path.includes('international-relations')){assert.equal(await page.getByRole('tab').count(),9);await page.getByRole('tab',{name:'Agreements and contracts'}).click();assert(await page.locator('.cooperation-agreement-card:visible').count());await page.getByPlaceholder('Partner, call, country…').fill('Erasmus');assert(await page.locator('.cooperation-search-results').isVisible());}
    if(path==='/en'){assert((await page.locator('.hero-slide').count()) > 0);assert(await page.getByRole('navigation',{name:'Languages'}).locator('a[lang=bs]').count());}
    await page.screenshot({path:'migration/output/en-'+viewport.width+'-'+(path.slice(1).replaceAll('/','_')||'home')+'.png',fullPage:path==='/en' || /contact|kontakt/.test(path)});
    result.push({path,width:viewport.width,overflow});
   }
   assert.deepEqual(errors,[]);await page.close();
  }
  fs.writeFileSync('migration/output/language-browser-check.json',JSON.stringify({status:'passed',pages:result},null,2));console.log(JSON.stringify({status:'passed',pages:result.length}));
 }finally{await browser.close()}
})().catch(e=>{console.error(e.stack);process.exit(1)});
