# CEPS administracija — PHP i MySQL

Jedan administratorski račun, na `/admin`. Stare `/Admin` adrese vode na novi ulaz.

## Što radi

- Novosti: pretraga, dodavanje i uređivanje, vizualni editor i opcionalni HTML, jezici i postojeće oznake za studije/projekte.
- Slike/PDF: prijenos u editoru i kod sporazuma, biblioteka prenesenih datoteka. JPG/PNG/WebP do 12 MB i 40 MP; PDF do 25 MB. Datoteke se spremaju izvan document roota i poslužuju kroz `/media/` s nasumičnim nazivima i provjerenim vrstama sadržaja.
- Nacrti: posebna tablica, sadržaj nije dostupan na javnom webu. Prenesena datoteka je dostupna preko svoje poveznice i prije objave; ne koristiti biblioteku za povjerljive dokumente.
- Pregled prije objave, izričita radnja Objavi promjene.
- Povijest izmjena novosti i sporazuma: učitaj stariju verziju, pregledaj, spremi nacrt ili objavi. Učitavanje ne mijenja javni sadržaj.
- Sporazumi: naslov, PDF, kategorija privreda/školstvo, datum, aktivnost i redoslijed.
- Naslovnica: naslovi i opisi postojećih slajdova, slike, video poveznice, redoslijed i uključivanje; popup slika, poveznica, opis i uključivanje. Automatsko zatvaranje ostaje 15 sekundi. Povijest postavki omogućuje povrat.
- Prijava: hash lozinke, CSRF, obnova sesije pri prijavi, istek nakon 30 minuta neaktivnosti, 5 neuspjelih prijava po IP-u tijekom 15 minuta, HTTPS u produkciji, noindex i no-store.
- Zaštita od prepisivanja paralelnih izmjena; objavljivanje nacrta i revizije koriste transakcije.

## Početak na ovom računalu

Tablice su instalirane u lokalni MySQL `ceps_preview`. Postojeći javni zapisi nisu prepisani. Preview je ponovno pokrenut s fileinfo podrškom.

Iz terminala projekta:

```powershell
.\php-app\setup-admin.ps1
```

Unesite korisničko ime (3–60 znakova: slova, brojevi, točka, crtica ili donja crta) i lozinku od najmanje 14 znakova. PowerShell skriva unos lozinke. Ne slati lozinku kroz chat. Skripta odbija prepisivanje postojećeg računa.

Zatim otvorite http://127.0.0.1:8086/admin.

Probni postupak: dodaj novost → unesi naslov i tekst → dodaj sliku → Spremi nacrt → Nacrti → Pregled → Objavi promjene. Sporazum: odaberi kategoriju, prenesi PDF, uključi Aktivan i objavi.

## cPanel / PHP / MySQL

1. Prenijeti aplikaciju uz document root `php-app/public`; sadržaj `src`, `content`, `var` i `bin` mora ostati izvan javnog direktorija.
2. Konfigurirati `CEPS_DSN`, `CEPS_DB_USER`, `CEPS_DB_PASSWORD`, `CEPS_ORIGIN` i produkcijski HTTPS. Obavezne ekstenzije: PDO MySQL, DOM, fileinfo, iconv (te postojeće ekstenzije javne aplikacije).
3. Na sigurnosnoj kopiji / uvezenoj bazi pokrenuti `php bin/install-admin.php`. Kreira AdminDrafts, AdminRevisions, AdminMedia i AdminSettings bez izmjene izvornih tablica. Migraciju pokreće korisnik s CREATE ovlasti; aplikacijskom korisniku nakon toga ne trebaju DDL ovlasti.
4. Postaviti administratorski račun pomoću `php bin/create-admin.php`. `var/admin.json` sadrži samo hash; čuvati izvan public direktorija. Opcionalno `CEPS_ADMIN_CONFIG` postavlja drugu privatnu lokaciju. CLI unos na običnom terminalu može biti vidljiv.
5. `var` / `CEPS_MEDIA_DIR` trebaju biti zapisivi PHP procesu, bez izvršavanja uploadanih datoteka. Postaviti `upload_max_filesize=25M`, `post_max_size=30M` ili više, prema limitima hostinga.
6. Backup mora uključivati MySQL, privatnu mapu medija i admin konfiguraciju. Ne prenijeti `var/ui-test`, testne datoteke ni lokalne logove/DB lozinke na produkciju.
7. Provjeriti login, nacrt, sliku, PDF, objavu, vraćanje verzije i HTTPS sesiju na stvarnom hostingu prije zamjene starog weba.

Postojeće stranice iz JSON sadržaja nisu univerzalni CMS editor; ova administracija pokriva dogovorene operativne module. Trajno brisanje nije ponuđeno; sporazum se može deaktivirati. Vizualni editor koristi lokalni JS i standardno uređivanje preglednika; ne traži vanjski servis ni API ključ. Podržan je HTML prikaz kao rezerva.

## Provjere

- `python php-app/tests/admin_check.py`: izolirani HTTP server na 8089 i privremena SQLite baza; prijava/CSRF, uređivanje, konkurentne izmjene, privatni nacrti, pregled/objava, revizije, PDF upload, odbijanje izvršivih datoteka, postavke naslovnice, odjava i ograničenje prijave.
- `php -d extension=pdo_mysql tests/admin_mysql_check.php`: lokalna MariaDB/MySQL provjera u zasebnoj bazi koja se briše nakon testa; stvarni sadržaj ostaje netaknut. Test koristi lokalni runtime i port 33079, nije produkcijska instalacijska skripta.
- U pregledniku je provjeren vizualni editor i tijek spremanja nacrta, pregleda te objave u izdvojenoj testnoj bazi.

Nije objavljeno na produkcijskom hostingu. Produkcijska provjera ovisi o pristupu cPanelu i njegovim postavkama.
