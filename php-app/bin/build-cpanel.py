"""Build separate private/public cPanel archives; never put credentials in Git."""
from pathlib import Path
import gzip
import shutil
import zipfile

app = Path(__file__).resolve().parents[1]
out = app.parent / 'deploy' / 'ceps-test'
out.mkdir(parents=True, exist_ok=True)
config = '''<?php
return [
    'CEPS_ENV' => 'preview',
    'CEPS_ORIGIN' => 'https://ceps.doter.info',
    'CEPS_DSN' => 'mysql:host=localhost;dbname=credoce1_ceps_test;charset=utf8mb4',
    'CEPS_DB_USER' => 'credoce1_ceps_test',
    'CEPS_DB_PASSWORD' => 'OVDJE_UNESI_LOZINKU_BAZE',
    'CEPS_CONTACT_ENABLED' => '0',
];
'''
wrapper = '''<?php
declare(strict_types=1);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$app = dirname(__DIR__, 2).'/ceps-test/php-app';
$config = require $app.'/config.local.php';
foreach ($config as $name=>$value) putenv($name.'='.$value);
require $app.'/public/index.php';
'''
htaccess = '''Options -Indexes -MultiViews
DirectoryIndex index.php
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://ceps.doter.info%{REQUEST_URI} [R=302,L]
# Legacy assets remain on the existing CEPS website during testing.
RewriteRule ^[Ff]iles/(.*)$ https://ceps.edu.ba/Files/$1 [R=302,L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]
<IfModule mod_headers.c>
Header always set X-Robots-Tag "noindex, nofollow"
</IfModule>
'''
with zipfile.ZipFile(out/'01-private.zip', 'w', zipfile.ZIP_DEFLATED) as z:
    for directory in ['src', 'content']:
        for file in sorted((app/directory).rglob('*')):
            if file.is_file(): z.write(file, 'ceps-test/php-app/'+file.relative_to(app).as_posix())
    z.write(app/'public/index.php', 'ceps-test/php-app/public/index.php')
    z.writestr('ceps-test/php-app/config.local.php', config)
    z.writestr('ceps-test/.htaccess', 'Require all denied\n')
    if not (app/'var/admin.json').is_file(): raise SystemExit('Create the local admin account first.')
    z.write(app/'var/admin.json', 'ceps-test/php-app/var/admin.json')
    for file in sorted((app/'var/media').glob('*')):
        if file.is_file(): z.write(file, 'ceps-test/php-app/var/media/'+file.name)
with zipfile.ZipFile(out/'02-public.zip', 'w', zipfile.ZIP_DEFLATED) as z:
    for file in sorted((app/'public/assets').rglob('*')):
        if file.is_file(): z.write(file, file.relative_to(app/'public').as_posix())
    z.writestr('index.php', wrapper)
    z.writestr('.htaccess', htaccess)
sql = out/'03-database.sql'
if sql.exists():
    with sql.open('rb') as src, gzip.open(out/'03-database.sql.gz', 'wb') as dst:
        shutil.copyfileobj(src, dst)
for name in ['01-private.zip', '02-public.zip', '03-database.sql.gz']:
    file = out/name
    if file.exists(): print(f'{name}: {file.stat().st_size:,} bytes')
