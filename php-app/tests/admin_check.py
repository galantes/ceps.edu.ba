"""Admin integration checks; uses disposable credentials and an isolated SQLite DB."""
import os, json, sqlite3, tempfile, subprocess, time, re, secrets
from pathlib import Path
from urllib.request import build_opener, HTTPCookieProcessor, HTTPRedirectHandler, Request
from urllib.parse import urlencode
from urllib.error import HTTPError, URLError
ROOT=Path(__file__).resolve().parents[2]
PHP=ROOT/'migration/runtime/php/php.exe'
class NoRedirect(HTTPRedirectHandler):
 def redirect_request(self,*args): return None
with tempfile.TemporaryDirectory() as temp:
 temp=Path(temp); db=temp/'test.sqlite';config=temp/'admin.json'
 source=sqlite3.connect(ROOT/'migration/output/staging.sqlite');target=sqlite3.connect(db)
 for table in ['Novosti','Sporazumi']:
  target.execute(source.execute('SELECT sql FROM sqlite_master WHERE name=?',(table,)).fetchone()[0])
 target.commit();target.close();source.close()
 password=secrets.token_urlsafe(24)
 hashed=subprocess.check_output([str(PHP),'-r','echo password_hash(trim(stream_get_contents(STDIN)), PASSWORD_DEFAULT);'],input=password.encode()).decode()
 config.write_text(json.dumps({'username':'test-admin','password_hash':hashed}))
 env=os.environ.copy();env.update(CEPS_ADMIN_CONFIG=str(config),CEPS_DSN='sqlite:'+str(db),CEPS_ENV='preview',CEPS_MEDIA_DIR=str(temp/'media'))
 subprocess.check_call([str(PHP),'-d','extension_dir='+str(PHP.parent/'ext'),'-d','extension=pdo_sqlite',str(ROOT/'php-app/bin/install-admin.php')],env=env,stdout=subprocess.DEVNULL)
 server=subprocess.Popen([str(PHP),'-d','extension_dir='+str(PHP.parent/'ext'),'-d','extension=pdo_sqlite','-d','extension=fileinfo','-S','127.0.0.1:8089','-t',str(ROOT/'php-app/public'),str(ROOT/'php-app/router.php')],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
 client=build_opener(HTTPCookieProcessor(),NoRedirect)
 def fetch(path='/admin',data=None):
  try:r=client.open(Request('http://127.0.0.1:8089'+path,data=urlencode(data).encode() if data is not None else None),timeout=5)
  except HTTPError as e:r=e
  return r.status,r.headers,r.read().decode()
 def token(body,name='csrf'):return re.search('name="'+name+'" value="([^"]*)"',body).group(1)
 try:
  for _ in range(30):
   try:status,h,b=fetch();break
   except URLError:time.sleep(.1)
  assert status==200 and 'Prijava' in b
  assert fetch('/admin',{'action':'logout'})[0]==403
  assert fetch('/admin',{'csrf':token(b),'username':'test-admin','password':password})[0]==303
  status,h,b=fetch('/admin?module=news&id=new');assert status==200
  data={'csrf':token(b),'version':token(b,'version'),'Naslov':'Test novost','Tekst':'<p>Test sadržaj</p>','Datum':'2026-09-29T12:30','Hr':'1','projekt_1':'1'}
  status,h,b=fetch('/admin?module=news&id=new',data);assert status==303,(status,b[-600:])
  status,h,b=fetch('/admin?module=news&id=1');data.update(csrf=token(b),version=token(b,'version'),Naslov='Promjena naslova')
  assert fetch('/admin?module=news&id=1',data)[0]==303
  assert 'međuvremenu' in fetch('/admin?module=news&id=1',data)[2]
  status,h,b=fetch('/admin?module=agreements&id=new')
  agreement={'csrf':token(b),'version':token(b,'version'),'Naziv':'Test sporazum','Datum':'2026-09-29T12:30','Putanja':'/Files/test.pdf','Kategorija':'privreda','Sort':'5','Aktivan':'1'}
  assert fetch('/admin?module=agreements&id=new',agreement)[0]==303
  agreement['Putanja']='javascript:alert(1)';assert 'Unesite PDF' in fetch('/admin?module=agreements&id=new',agreement)[2]
  status,h,b=fetch('/admin?module=media');csrf=token(b)
  def upload(name,body):
   boundary='ceps-test-boundary'
   payload=(f'--{boundary}\r\nContent-Disposition: form-data; name="csrf"\r\n\r\n{csrf}\r\n--{boundary}\r\nContent-Disposition: form-data; name="file"; filename="{name}"\r\nContent-Type: application/octet-stream\r\n\r\n').encode()+body+f'\r\n--{boundary}--\r\n'.encode()
   req=Request('http://127.0.0.1:8089/admin?module=upload',data=payload,headers={'Content-Type':'multipart/form-data; boundary='+boundary})
   try:r=client.open(req)
   except HTTPError as e:r=e
   return r.status,json.loads(r.read())
  status,file=upload('test.pdf',b'%PDF-1.4\n1 0 obj <</Type /Catalog>> endobj\n%%EOF')
  assert status==200,(status,file)
  status,h,b=fetch(file['url']);assert status==200 and h['Content-Type']=='application/pdf' and 'sandbox' in h['Content-Security-Policy']
  status,image=upload('photo.jpg',(ROOT/'Files/Images/studiji.jpg').read_bytes());assert status==200
  assert client.open('http://127.0.0.1:8089'+image['url']).headers['Content-Type']=='image/jpeg'
  assert upload('evil.php',b'<?php echo 1; ?>')[0]==400
  assert fetch('/media/../../src/admin.php')[0]==404
  status,h,b=fetch('/admin?module=news&id=new')
  draft={'csrf':token(b),'version':token(b,'version'),'Naslov':'Privatni nacrt','Tekst':'<p>Neobjavljeno</p>','Datum':'2026-09-29T12:30','Hr':'1','action':'draft'}
  status,h,b=fetch('/admin?module=news&id=new',draft);assert status==303
  draft_url=h['Location'];assert '&draft=' in draft_url
  assert 'Privatni nacrt' not in fetch('/novosti')[2]
  status,h,b=fetch(draft_url);draft.update(csrf=token(b),version=token(b,'version'),_draft_version=token(b,'_draft_version'),action='preview')
  assert 'PREGLED' in fetch(draft_url,draft)[2]
  draft['action']='publish';assert fetch(draft_url,draft)[0]==303
  assert fetch(draft_url,draft)[0]==404
  assert 'Privatni nacrt' in fetch('/novosti')[2]
  assert 'Učitaj verziju' in fetch('/admin?module=news&id=1')[2]
  from html.parser import HTMLParser
  class Fields(HTMLParser):
   def __init__(self):super().__init__();self.data={}
   def handle_starttag(self,tag,attrs):
    a=dict(attrs)
    if tag=='input' and 'name' in a and (a.get('type')!='checkbox' or 'checked' in a):self.data[a['name']]=a.get('value','1')
  status,h,b=fetch('/admin?module=home');fields=Fields();fields.feed(b);settings=fields.data
  settings['title_0']='Novi naslov slajda';settings.pop('popup_enabled',None)
  assert fetch('/admin?module=home',settings)[0]==303
  homepage=fetch('/')[2];assert 'Novi naslov slajda' in homepage and 'class="home-announcement"' not in homepage

  con=sqlite3.connect(db);assert con.execute('select count(*) from Novosti').fetchone()[0]==2;assert con.execute('select count(*) from Sporazumi').fetchone()[0]==1;con.close()
  # Deletion is confirmed separately, CSRF protected, and draft-only deletion preserves live news.
  status,h,b=fetch('/admin?module=news&id=1')
  delete_draft={'csrf':token(b),'version':token(b,'version'),'Naslov':'Nacrt za brisanje','Datum':'2026-09-29T12:00','Tekst':'<p>Test nacrta</p>','Hr':'1','action':'draft'}
  status,h,_=fetch('/admin?module=news&id=1',delete_draft);assert status==303
  from urllib.parse import urlparse,parse_qs
  draft_id=parse_qs(urlparse(h['Location']).query)['draft'][0]
  delete_url='/admin?module=drafts&delete='+draft_id
  status,h,b=fetch(delete_url);assert status==200 and 'Da, izbriši nacrt' in b
  assert fetch(delete_url,{'action':'delete','version':token(b,'version')})[0]==403
  assert fetch(delete_url,{'csrf':token(b),'action':'delete','version':'stale'})[0]==409
  assert fetch(delete_url,{'csrf':token(b),'action':'delete','version':token(b,'version')})[0]==303
  con=sqlite3.connect(db);assert con.execute('select count(*) from Novosti').fetchone()[0]==2;con.close()
  status,h,b=fetch('/admin?module=news&delete=1');assert status==200 and 'Da, izbriši novost' in b
  con=sqlite3.connect(db);assert con.execute('select count(*) from Novosti where ID=1').fetchone()[0]==1;con.close()
  assert fetch('/admin?module=news&delete=1',{'csrf':token(b),'action':'delete','version':token(b,'version')})[0]==303
  con=sqlite3.connect(db);assert con.execute('select count(*) from Novosti where ID=1').fetchone()[0]==0;con.close()
  assert fetch('/admin?module=news&delete=1')[0]==404
  assert fetch('/admin?module=home&delete=1')[0]==404
  print('PASS: confirmed news/draft deletion, CSRF, stale confirmation, GET safety and draft isolation.')
  assert fetch('/admin',{'csrf':token(b),'action':'logout'})[0]==303
  assert 'Prijava' in fetch('/admin?module=news&id=1')[2]
  status,h,b=fetch();csrf=token(b)
  for _ in range(5):fetch('/admin',{'csrf':csrf,'username':'test-admin','password':'bad'})
  assert 'Previše pokušaja' in fetch('/admin',{'csrf':csrf,'username':'test-admin','password':password})[2]
  print('PASS: login, CSRF, create/edit, concurrent edit protection, agreements, drafts/private preview/publish, revisions, PDF upload, executable rejection, logout, rate limiting; isolated database only.')
 finally:server.terminate();server.wait(timeout=5)

