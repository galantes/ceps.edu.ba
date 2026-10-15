from concurrent.futures import ThreadPoolExecutor,as_completed
from pathlib import Path
import urllib.request,urllib.error,urllib.parse,json,re,html,time,hashlib,threading,xml.etree.ElementTree as ET
root=Path(__file__).resolve().parents[2]
fixture=json.loads((root/'migration/output/language-fixture.json').read_text(encoding='utf-8'))
base='http://127.0.0.1:8086'
rate_lock=threading.Lock()
next_request=0.0
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args): return None
opener=urllib.request.build_opener(NoRedirect)
def request(path,host=None,method='GET'):
 global next_request
 with rate_lock:
  now=time.monotonic();delay=max(0,next_request-now);next_request=max(now,next_request)+0.04
 if delay:time.sleep(delay)
 req=urllib.request.Request(base+path,headers={'Host':host} if host else {},method=method)
 try:
  with opener.open(req,timeout=35) as res:return res.status,res.headers,res.read().decode('utf-8','replace')
 except urllib.error.HTTPError as res:return res.code,res.headers,res.read().decode('utf-8','replace')
jobs=[]
for row in fixture['news']:
 for old in [f"/Novost.aspx?id={row['id']}",f"/Novost?id={row['id']}"]:
  jobs.append((old,None,row['bs']))
 jobs.append((f"/en/Novost.aspx?id={row['id']}",None,row['en']))
 jobs.append((f"/Novost.aspx?id={row['id']}",'en.ceps.edu.ba','https://ceps.edu.ba'+row['en']))
for en in [False,True]:
 for old,new in fixture['en_routes' if en else 'bs_routes'].items():
  source=('/en' if en else '')+(('/Default.aspx') if old=='/' else old+'.aspx')
  target=('/en' if en else '')+('' if en and new=='/' else new)
  jobs.append((source,None,target))
  if en:jobs.append((old+'.aspx' if old!='/' else '/Default.aspx','en.ceps.edu.ba','https://ceps.edu.ba'+target))
errors=[];checked=0;start=time.time()
def redirect_check(job):
 path,host,expected=job;status,headers,_=request(path,host,method='HEAD')
 if status!=301 or headers.get('Location')!=expected:return {'path':path,'host':host,'status':status,'actual':headers.get('Location'),'expected':expected}
with ThreadPoolExecutor(max_workers=5) as pool:
 for future in as_completed([pool.submit(redirect_check,j) for j in jobs]):
  error=future.result();checked+=1
  if error:
   errors.append(error);print(json.dumps(error),flush=True)
  if checked%1000==0:print(f'{checked}/{len(jobs)} redirects checked',flush=True)
print(f'Redirect checks complete: {len(errors)} errors',flush=True)
# Every distinct article target must resolve to the preserved ID.
targets={r['bs']:r for r in fixture['news']}
targets.update({r['en']:r for r in fixture['news'] if r['english']})
def article_check(item):
 path,row=item;status,headers,body=request(path)
 expected='https://ceps.edu.ba'+path
 if status!=200 or f'data-news-id="{row["id"]}"' not in body or f'rel="canonical" href="{html.escape(expected,quote=True)}"' not in body:return {'article':path,'status':status,'id':row['id']}
with ThreadPoolExecutor(max_workers=4) as pool:
 for future in as_completed([pool.submit(article_check,item) for item in targets.items()]):
  error=future.result()
  if error:
   errors.append(error);print(json.dumps(error),flush=True)
print(f'{len(targets)} article targets checked',flush=True)
for old,new in fixture['en_routes'].items():
 path='/en'+('' if new=='/' else new)
 status,headers,body=request(path)
 if status!=200 or '<html lang="en">' not in body:errors.append({'page':path,'status':status})
 if old=='/Kontakt' and re.search(r'<(?:form|input|textarea)\b',body):errors.append({'contact_form_found':path})
status,headers,body=request('/kontakt')
if status!=200 or re.search(r'<(?:form|input|textarea)\b',body):errors.append({'contact_form_found':'/kontakt'})
english_ids={r['id'] for r in fixture['news'] if r['english']};seen=set()
for page in range(1,(len(english_ids)+11)//12+1):
 status,_,body=request('/en/news'+(f'?page={page}' if page>1 else ''))
 ids={int(i) for i in re.findall(r'href="/en/news/(\d+)-',body)}
 if status!=200 or not ids.issubset(english_ids):errors.append({'archive_page':page,'status':status,'invalid_ids':list(ids-english_ids)})
 seen.update(ids)
if seen!=english_ids:errors.append({'archive_missing':list(english_ids-seen)})
status,_,body=request('/sitemap.xml');doc=ET.fromstring(body);ns={'s':'http://www.sitemaps.org/schemas/sitemap/0.9'};locations={n.text for n in doc.findall('s:url/s:loc',ns)}
for row in fixture['news']:
 for lang,enabled in [('bs',row['bosnian']),('en',row['english'])]:
  if enabled and 'https://ceps.edu.ba'+row[lang] not in locations:errors.append({'sitemap_missing':row[lang]})
for path,expected in [('/en/Novost.aspx?id=999999999',404),('/Novost.aspx?id=999999999',404),('/en/does-not-exist',404),('/kontakt',405),('/en/contact',405)]:
 status,_,_=request(path,method='POST' if expected==405 else 'GET')
 if status!=expected:errors.append({'negative':path,'expected':expected,'status':status})
files=json.loads((root/'php-app/content/en/files.json').read_text(encoding='utf-8'))
for path,meta in files.items():
 encoded=urllib.parse.quote('/'+path,safe='/')
 with opener.open(base+encoded,timeout=35) as response:
  data=response.read()
  if response.status!=200 or hashlib.sha256(data).hexdigest()!=meta['sha256']:errors.append({'document_bytes':path})
 status,headers,_=request(encoded,'en.ceps.edu.ba',method='HEAD')
 if status!=301 or headers.get('Location')!='https://ceps.edu.ba'+encoded:errors.append({'old_document_redirect':path,'status':status})
for source,target in json.loads((root/'php-app/content/en/file-redirects.json').read_text(encoding='utf-8')).items():
 encoded=urllib.parse.quote(source,safe='/');expected=urllib.parse.quote(target,safe='/')
 for host in [None,'en.ceps.edu.ba']:
  status,headers,_=request(encoded,host,method='HEAD')
  if status!=301 or headers.get('Location')!=('https://ceps.edu.ba' if host else '')+expected:errors.append({'file_alias':source,'host':host,'status':status,'actual':headers.get('Location')})
report={'status':'passed' if not errors else 'failed','redirects':len(jobs),'article_targets':len(targets),'english_news':len(english_ids),'english_archive_ids_verified':len(seen),'static_routes':len(fixture['en_routes']),'sitemap_urls':len(locations),'original_pdfs':len(files),'seconds':round(time.time()-start,1),'errors':errors}
(root/'migration/output/language-http-check.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8');print(json.dumps(report,ensure_ascii=False,indent=2),flush=True)
raise SystemExit(bool(errors))
