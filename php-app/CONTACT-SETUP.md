# Kontakt obrazac

Novi obrazac na `/Kontakt` koristi PHP mail transport hostinga. Stari `/Mail/form.php` nije prenesen: javni obrazac prikazuje polja bez gumba za slanje.

Slanje je po zadanim postavkama isključeno. Nakon konfiguriranja poštanskog transporta na cPanel hostingu postaviti varijable okruženja:

- `CEPS_CONTACT_ENABLED=1`
- `CEPS_CONTACT_FROM`: valjana adresa pošiljatelja s domene koju hosting smije koristiti.

Primatelj ostaje `info@ceps.edu.ba`. Korisnikov e-mail koristi se samo kao Reply-To. Obrazac ima provjeru obaveznih polja i duljine, CSRF token, honeypot i ograničenje ponovnog slanja unutar iste sesije. Prije javne objave dodati ograničenje broja zahtjeva na razini hostinga i provjeriti dostavu testnom porukom uz odobrenje korisnika. Povratna vrijednost mail() potvrđuje predaju transportu, ne dostavu primatelju.

Lokalno nisu slane poruke niti mijenjane postavke produkcijske pošte.
