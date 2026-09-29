"""Exhaustive news URL checks against the running local PHP preview."""
from pathlib import Path
from urllib.request import build_opener, HTTPRedirectHandler, Request
from urllib.error import HTTPError
from urllib.parse import quote, urlsplit
import json
import csv
import re
import time

ROOT = Path(__file__).resolve().parents[2]
BASE = 'http://127.0.0.1:8086'


class NoRedirect(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


client = build_opener(NoRedirect)


def fetch(path, method='GET'):
    try:
        response = client.open(Request(BASE + path, method=method), timeout=15)
    except HTTPError as error:
        response = error
    with response:
        return response.status, response.headers, response.read()


started = time.monotonic()
news = json.loads((ROOT / 'migration/output/Novosti.json').read_text(encoding='utf-8'))
for index, row in enumerate(news):
    status, headers, _ = fetch('/Novost?id=' + str(row['ID']))
    assert status == 301
    canonical = headers['Location']
    assert canonical.startswith('/novosti/' + str(row['ID']) + '-')
    status, headers, body = fetch(canonical)
    assert status == 200, (canonical, status)
    assert f'data-news-id="{row["ID"]}"'.encode() in body, canonical
    assert f'href="https://www.ceps.edu.ba{canonical}"'.encode() in body, canonical
    assert b'<title>Novosti i objave' not in body, canonical
    assert headers.get('X-Robots-Tag') == 'noindex,nofollow'
    status, headers, _ = fetch('/Novost.aspx?id=' + str(row['ID']))
    assert status == 301 and headers.get('Location') == canonical, canonical
    if (index + 1) % 500 == 0:
        print(f'Checked {index + 1} news entries', flush=True)

for path in ['/Novost', '/Novost?id=54', '/Novost?id=999999', '/Novost?id=-1', '/Novost?id[]=1', '/Novost?id=1%20OR%201=1', '/novosti?page=0', '/novosti?page=999999', '/missing-page', '/migration/output/Novosti.json', '/ceps_baza_skripta.sql', '/src/bootstrap.php', '/Files/../web.config']:
    assert fetch(path)[0] == 404, path
assert fetch('/Novost?id=1', 'POST')[0] == 405
assert fetch('/Novost?id=1', 'HEAD')[2] == b''
assert fetch('/nOvOsT.AsPx?ID=0001')[1].get('Location') == fetch('/Novost?id=1')[1]['Location']
assert fetch('/Novost?id=1&utm_source=check')[1]['Location'].endswith('?utm_source=check')
assert b'Disallow: /' in fetch('/robots.txt')[2]
for filename in ['pages', 'projects']:
    entries = json.loads((ROOT / 'php-app/content' / (filename + '.json')).read_text(encoding='utf-8-sig'))
    for entry in entries.values():
        old = '/' + entry['slug']
        status, headers, _ = fetch(old + '.aspx')
        assert status == 301, old
        target = headers['Location']
        assert fetch(target)[0] == 200, target
        assert fetch(target + '/')[1]['Location'] == target
assert fetch('/novosti/1-outdated-title')[1]['Location'] == fetch('/Novost?id=1')[1]['Location']
assert fetch('/kontakt', 'POST')[0] == 200
assert fetch('/Kontakt', 'POST')[0] == 200
pages = (sum(row['Hr'] == 1 for row in news) + 11) // 12
seen_ids = []
for page in range(1, pages + 1):
    status, _, body = fetch('/novosti' + (f'?page={page}' if page > 1 else ''))
    assert status == 200
    seen_ids.extend(int(i) for i in re.findall(rb'<h3><a href="/novosti/(\d+)-', body))
assert len(seen_ids) == len(set(seen_ids))
assert set(seen_ids) == {row['ID'] for row in news if row['Hr'] == 1}

with (ROOT / 'migration/output/internal-link-review.csv').open(encoding='utf-8-sig') as stream:
    cases = {row['actual_path'] for row in csv.DictReader(stream) if row['issue'] == 'case_mismatch' and row['actual_path'].startswith('Files/')}
assets = 0
for path in sorted(cases):
    status, headers, _ = fetch('/' + quote(path.swapcase(), safe='/'), 'HEAD')
    assert status == 301, (path, status)
    assert fetch(headers['Location'], 'HEAD')[0] == 200, path
    assets += 1
report = {'news_200': len(news), 'legacy_news_301': len(news), 'archive_pages': pages, 'archive_unique_hr_news': len(seen_ids), 'case_compatible_files': assets, 'negative_and_security_checks': 'passed', 'seconds': round(time.monotonic() - started, 2)}
(ROOT / 'migration/output/http-verification.json').write_text(json.dumps(report, indent=2), encoding='utf-8')
print(json.dumps(report, indent=2))
