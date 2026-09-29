> Nova administracija (prva faza): [ADMIN.md](ADMIN.md). SEO rute: [SEO-URLS.md](SEO-URLS.md). Stariji opis faze 2 ispod ne obuhvaća kasnije dogradnje.

# PHP pregled novosti — faza 2

Radi na PHP-u s PDO, PDO MySQL, DOM i UTF-8 podrškom. Nema vanjskih PHP paketa.
Probni uvoz provjeren je na prijenosnom PHP 8.4.26 i MariaDB 11.4.8 na Windowsu.
Linux hosting ostaje cilj; pokretanje pod Linuxom/Apacheom još nije provjereno.

## Pokretanje na ovom računalu

Iz korijena projekta u PowerShellu:

```powershell
./php-app/start-database.ps1
./php-app/start-preview.ps1
```

Pregled: http://127.0.0.1:8086/Novosti

Baza sluša samo na 127.0.0.1:33079, PHP samo na 127.0.0.1:8086.
Prijenosni runtime i lokalna lozinka su u `migration/runtime`; ne objavljivati tu mapu.
Preview se zaustavlja s Ctrl+C. Nije instaliran Windows servis.

## Implementirano

- `/Novosti`: hrvatska arhiva, 12 objava po stranici, redoslijed po datumu i ID-u.
- `/Novost?id=ID`: sve objave, uključujući engleske, dostupne po izvornom ID-u.
- `/Novost.aspx?id=ID`: jedno 301 preusmjeravanje na odgovarajući stari URL bez ekstenzije.
- Nepostojeći/pogrešni ID-evi vraćaju 404. Greška baze vraća 503.
- Naslov, opis i canonical za svaku objavu. Preview je noindex i robots.txt blokira indeksiranje.
- Poveznice i slike s ceps.edu.ba u tijelu objave vode na lokalne rute u pregledu.
- Datoteke iz manifesta Files dostupne su kroz provjerenu putanju; razlike u veličini slova preusmjeravaju na stvarnu putanju.
- Ugrađene stare galerije/video/iframe sadržaji prikazani su kao poveznice. Tekst baze nije mijenjan. Nova vizualna obrada HTML-a uklanja stare inline stilove i aktivne skripte.

Na arhivi se prikazuje 956 hrvatskih objava jer stari web filtrira Hr=1. Svih
1.555 objava postoji u bazi i dostupno je direktno. Jezična navigacija slijedi kasnije.

## Provjere

- `migration/output/mysql-verification.json`: 1.811 zapisa / 51.808 polja uspoređenih sa svim izvornim JSON vrijednostima; datetime format normaliziran bez promjene vremena.
- `migration/output/http-verification.json`: svaki ID provjeren na obje stare rute, sve stranice arhive, case-sensitive putanje te negativni i sigurnosni slučajevi.
- Ponoviti HTTP provjeru dok preview radi: `python php-app/tests/http_check.py`.
- Parser migracije: `python -m unittest discover -s migration -p "test_*.py"`.
- Render test: `php -n php-app/tests/render_check.php`.

`bin/import-preview.php` kreira novu bazu i odbija prepisati postojeću. Za ponovnu
usporedbu koristiti `--verify-only`. Konfiguracija kroz CEPS_SERVER_DSN,
CEPS_IMPORT_DATABASE, CEPS_DB_USER, CEPS_DB_PASSWORD. Podaci za produkciju ne spremaju
se u javnu mapu. Uvoz koristi administrativnog korisnika samo lokalne radne baze;
produkcijska aplikacija treba zasebnog korisnika s minimalnim ovlastima.

## Još nije za objavu

Ovo je funkcionalna faza novosti, ne cijeli migrirani web. Ostale stranice,
administracija, forme, Thumb.ashx, potpuna obrada svih vrsta priloga, sitemap i slug-rute
još nisu implementirani. Naslovnica privremeno vraća 302 na arhivu.
Ne postavljati CEPS_ENV=production i ne mijenjati DNS u ovoj fazi.

Za Linux: document root mora biti `php-app/public`, uz rewrite na index.php;
aplikacija i podaci izvan javne mape. Apache primjer je u public/.htaccess.
CEPS_DSN=mysql:host=...;dbname=...;charset=utf8mb4 i CEPS_ORIGIN=https://www.ceps.edu.ba.
Prije objave treba zamijeniti preview robots.txt, dovršiti rute, obaviti puni Linux
HTTP test i riješiti preostale nedostajuće interne datoteke.
