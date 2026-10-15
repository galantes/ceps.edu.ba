> Nova administracija (prva faza): [ADMIN.md](ADMIN.md). SEO rute: [SEO-URLS.md](SEO-URLS.md). Stariji opis faze 2 ispod ne obuhvaća kasnije dogradnje.

## FTP paket u jednom folderu (aktuelno)

Pokrenuti `python php-app/bin/build-ftp.py` iz korijena projekta. Pripremljeni
`deploy/ceps-ftp/ceps.edu.ba/` sadrži javne datoteke i zaštićeni `app/` sa
konfiguracijom, izvornim kodom, administratorskim podacima, logovima i SQL izvozom.
Sadržaj tog foldera prenosi se u `public_html/ceps.edu.ba/`, uključujući `.htaccess`.
Nisu potrebne konfiguracije izvan tog foldera. Upute su u `app/UPUTE.txt`.

MySQL bazu treba kreirati i uvesti kroz cPanel/phpMyAdmin, a pristupne podatke
unijeti u `app/config.local.php`. Apache mora podržavati `.htaccess` i `Require all denied`;
na hostingu prije aktiviranja potvrditi da `/app/config.local.php` i `/app/var/admin.json`
vraćaju 403. Graditelj ne prepisuje postojeći config, administratorski račun ni medije.
Stare upute ispod opisuju raniju fazu razvoja i raspored sa privatnim folderom izvan weba.

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

## Engleska verzija i očuvanje starih URL-ova (14. 10. 2026.)

Engleska verzija koristi isti prikaz na `/en`. Novosti, arhiva i pretraga filtriraju `Novosti.En=1`. Projektne novosti nude
oba jezika: jezik trenutne stranice je prvi i početno odabran. Sporazumi koriste istu tabelu `Sporazumi` na oba
jezika. Opisi stranica su u `content/en/`; `sources.json` bilježi izvore, a
`overrides.json` sadrži provjerene zamjene za pogrešan jezik na starom EN webu.
Kontakt obrazac i obrada slanja su uklonjeni. Bosanski popup sa slikom upisa ne
prikazuje se u engleskoj verziji jer nema odgovarajući engleski grafički izvor.

Stari `/Novost.aspx?id=ID` i `/Novost?id=ID` zadržavaju izvorni ID i vraćaju 301
na `/novosti/ID-naslov`. Na starom `en.ceps.edu.ba` isti ID vodi na
`https://ceps.edu.ba/en/news/ID-title` kada je označen EN. Ako nije označen EN,
stari engleski link vodi na isti zapis na bosanskom, umjesto na 404 ili naslovnicu.
Stari nazivi podstranica također imaju pojedinačna 301 mapiranja. Novi slug
s netačnim/starim naslovom ponovo se određuje prema ID-u. Ostali query parametri
se čuvaju. Nepostojeći ID vraća 404.

Canonical, jezik dokumenta, Open Graph jezik i hreflang odgovaraju odabranoj
verziji. Odvojene jezičke novosti nemaju izmišljene međusobne hreflang veze.
`/sitemap.xml` sadrži obje jezičke verzije i samo novosti označene za taj jezik.
Preview ostaje noindex. Devet izvornih engleskih PDF-ova sačuvano je u
`content/en/files/` s izvornim URL-ovima i SHA-256 vrijednostima u `files.json`.
Dva pogrešno napisana stara linka časopisa imaju 301 na postojeće izvorne PDF-ove.

Prije objave na hostingu:

- Zadržati domenu `en.ceps.edu.ba`, DNS i važeći TLS certifikat. Usmjeriti je na
  isti document root kao `ceps.edu.ba`, kako bi aplikacija mogla odrediti cilj
  svakog starog URL-a. Ne postavljati opći redirect svih adresa na `/en`.
- Prenijeti izmijenjeni PHP kod, `content/en/`, javne assete i hosting `.htaccess`.
  Pravilo za stari engleski host mora biti prije općeg HTTPS/www pravila.
- Postaviti `CEPS_ORIGIN=https://ceps.edu.ba`. Tek nakon produkcijske provjere
  uključiti `CEPS_ENV=production` i prijaviti `/sitemap.xml` u Search Console.
- Ponoviti provjere na stvarnim domenama. Lokalni test s Host zaglavljem potvrđuje
  aplikacijsko mapiranje, ali ne potvrđuje DNS, TLS ni Apache na hostingu.

Provjere (koristiti lokalne PHP/Python/Node runtimeove):

1. `php -n -d extension=pdo_mysql tests/language_check.php ../migration/output/language-fixture.json`
   iz `php-app/` (podesiti `extension_dir` prema lokalnom PHP runtimeu).
2. Iz korijena: `python php-app/tests/language_http_check.py` dok radi preview.
   Provjerava svaki stari ID, stare EN host adrese, sve ciljne članke, sve EN
   arhivske stranice, podstranice, sitemap i odsustvo kontakt obrasca.
3. Iz korijena: `node php-app/tests/language_browser_check.cjs` uz Playwright.
   Provjerava kontakt, EN naslovnicu, studijske tabove, projekte i saradnju na
   širinama 1440 i 390 px. Slike provjere su u lokalnom `migration/output/`.

Ponovni uvoz engleskih izvora: `php bin/import-english-pages.php SOURCE_DIRECTORY`
iz `php-app/`. Ulazni HTML treba biti imenovan prema slugovima; `/` u projektnim
slugovima zamijeniti sa `__`. Uvoz primjenjuje spremljene prijevode i provjerene
zamjene; originalni HTML ostaje u ulaznom direktoriju.

Završna lokalna provjera: 1.567 zapisa novosti, 572 s EN oznakom; 8.047 provjera
mapiranja, 6.427 HTTP preusmjerenja i 2.139 ciljnih članaka. Svi prolaze bez greške.
Svih 572 EN zapisa pronađeno je kroz arhivu. Provjerene su 53 EN rute, 1.638 URL-ova
u sitemap-u i SHA-256 svih devet sačuvanih PDF-ova. Prikaz i interakcije provjereni
su na 1440 i 390 px, uključujući svih devet aktivnih slajdova. Veza s bazom sada
se ponovo koristi unutar istog zahtjeva, nakon uočenih povremenih grešaka pri
velikom broju uzastopnih lokalnih zahtjeva.
Lokalni izvještaji: `migration/output/language-http-check.json`,
`language-browser-check.json` i `language-slider-check.json`.
Ove provjere ne zamjenjuju provjeru DNS-a, TLS-a i Apache pravila na hostingu.

## Njemačka verzija (15. 10. 2026.)

Njemačka verzija je na `/de`, s naslovnicom, stranicom o ustanovi i 21 podstranicom
preuzetom s `de.ceps.edu.ba`. Izvori su evidentirani u `content/de/sources.json`.
Dokumenti i službeni nazivi ostaju sačuvani. Osam izvornih njemačkih PDF-ova
sačuvano je u `content/de/files/`, uz izvorne adrese i SHA-256 hashove u
`content/de/files.json`. Linkovi koriste lokalne kopije. Novosti se filtriraju
oznakom `De`; lokalna baza sadrži 35 njemačkih objava. Izbor jezika vodi na odgovarajuću stranicu,
a za članak bez prijevoda na arhivu tog jezika. Stranice bez njemačkog izvora
zadržavaju bosansku adresu i ne prikazuju se kao prevedene na `/de`.

Stari njemački host ima pripremljena 301 preusmjerenja na odgovarajuće `/de` adrese.
Za njihovu aktivaciju na hostingu potrebno je usmjeriti domenu na novu aplikaciju.
Njemačke stranice i članci uključeni su u sitemap i jezičke alternate oznake.

Ponovni uvoz: `php bin/import-german-pages.php SOURCE_DIRECTORY` iz `php-app/`.
Izvorni HTML treba biti imenovan prema starim slugovima, npr. `StudijIT.html`,
uz `Default.html` i `Onama.html`. Originali ostaju sačuvani u ulaznom direktoriju.

Provjera ruta: `php tests/german_check.php ../migration/output/german-fixture.json`
iz `php-app/`, uz lokalni PDO runtime kao u engleskoj provjeri. HTTP i vizuelna
provjera: `node php-app/tests/german_browser_check.cjs` iz korijena uz Playwright
i pokrenut preview. Provjerava njemačke stranice i članke, sitemap, stari host,
izbor jezika, studijske tabove i mobilni prelom naslova na 1440 i 390 px.
