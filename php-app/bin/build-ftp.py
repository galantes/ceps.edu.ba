"""Prepare one self-contained folder for FTP upload (no publishing)."""
from pathlib import Path
import shutil

app = Path(__file__).resolve().parents[1]
root = app.parent
source = root / 'deploy/ceps-ftp'
out = source / 'ceps.edu.ba'
private = out / 'app'
private.mkdir(parents=True, exist_ok=True)
for directory in ['src', 'content']:
    shutil.copytree(app / directory, private / directory, dirs_exist_ok=True)
(private / 'public').mkdir(exist_ok=True)
shutil.copy2(app / 'public/index.php', private / 'public/index.php')
shutil.copytree(app / 'public/assets', out / 'assets', dirs_exist_ok=True)
for name in ['index.php', '.htaccess']:
    shutil.copy2(app / 'hosting' / name, out / name)
(private / '.htaccess').write_text('Require all denied\n', encoding='utf-8')
(private / 'var/sessions').mkdir(parents=True, exist_ok=True)
(private / 'var/media').mkdir(parents=True, exist_ok=True)
for origin, target in [(source / 'config.local.php', private / 'config.local.php'),
                       (app / 'var/admin.json', private / 'var/admin.json')]:
    if not target.exists():
        if not origin.is_file():
            raise SystemExit(f'Nedostaje {origin.name}; konfiguracija nije kreirana.')
        shutil.copy2(origin, target)
# Preserve credentials, admin identity and uploaded media on subsequent builds.
for file in (app / 'var/media').glob('*'):
    if file.is_file() and not (private / 'var/media' / file.name).exists():
        shutil.copy2(file, private / 'var/media' / file.name)
shutil.copy2(root / 'migration/output/files.csv', private / 'content/files.csv')
if (source / '03-database.sql').is_file():
    (private / 'install').mkdir(exist_ok=True)
    shutil.copy2(source / '03-database.sql', private / 'install/03-database.sql')
shutil.copytree(root / 'stari-web/Files', out / 'Files', dirs_exist_ok=True)
# Legacy uploads must never execute as server scripts.
(out / 'Files/.htaccess').write_text(
    'Options -Indexes -ExecCGI\n<FilesMatch "(?i)\\.(?:php[0-9]*|phtml|phar|cgi|pl|py|sh|shtml|htaccess)$">\n    Require all denied\n</FilesMatch>\n',
    encoding='utf-8')
(private / 'UPUTE.txt').write_text("""FTP: jedan folder

Sadržaj deploy/ceps-ftp/ceps.edu.ba/ prenijeti u public_html/ceps.edu.ba/.
U tom direktoriju moraju biti index.php, .htaccess, assets/, Files/ i app/.
Upload uključuje skrivene .htaccess fajlove. Ništa se ne postavlja izvan tog foldera.
Domena i www moraju imati document root public_html/ceps.edu.ba.

PHP 8.2+; PDO/pdo_mysql, DOM/XML, mbstring, iconv, fileinfo i session.
Kreirati MySQL bazu i korisnika u cPanelu. Kroz phpMyAdmin u praznu bazu
importovati app/install/03-database.sql, samo jednom.
U app/config.local.php upisati host, puni naziv baze, korisnika i lozinku.
CEPS_ORIGIN postaviti na https://ceps.edu.ba. CEPS_ENV ostaviti preview do provjere;
production uključuje indeksiranje i tracking. Kontakt ostaje isključen do podešavanja pošte.
Postaviti SSL. app/var/ i app/var/sessions/ moraju biti upisivi PHP korisniku.
Direktoriji 755, javne datoteke 644; config.local.php i admin.json 600 ako hosting podržava.

Prije aktiviranja provjeriti naslovnu, novosti, admin, PDF, medije i robots.txt.
Direktni URL-ovi /app/config.local.php, /app/var/admin.json i /app/install/03-database.sql
moraju vratiti 403. Ako ne vraćaju 403, prvo ispraviti Apache .htaccess podršku.
SQL izvoz poslije uvoza može se ukloniti sa servera.
Kod ažuriranja ne prepisivati konfiguraciju, admin.json, var/, ni bazu.
Files i SQL su lokalne kopije: prije objave uskladiti sa aktivnim webom.
""", encoding='utf-8')
print(f'FTP folder: {out}')