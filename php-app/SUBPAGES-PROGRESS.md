# Napredak podstranica

49 sadržajnih podstranica: 36 osnovnih i 13 iz direktorija Projekt. Kontakt i projekti imaju namjenski raspored. Naslovnica, O nama, novosti i pretraga zasebno su implementirani.
Međunarodna suradnja: svi natječaji i aktivnosti iz baze, aktivni sporazumi privreda/skolstvo. Istraživački centar: projekt_15 Hr objave.
Galerija: 132 fotografije, poveznica na originalni YouTube kanal umjesto starog API widgeta. ALERRT: vraćene JavaScript serije fotografija. CRASE: 12 fotografija.
Kontakt: novi lokalni obrazac, kontakt kartice i izvorna Google karta. Slanje isključeno do konfiguracije pošte na hostingu (CONTACT-SETUP.md). Virtualna šetnja: izvorni Google embed.
Alumni, Maturanti i andragoška obuka: namjenske prijavne kartice vode izravno na izvorne Cognito obrasce (1, 4 i 3). Obrazac se otvara u novoj kartici. Ugrađeni prikaz lokalno ostaje prazan, pa nije ostavljen na stranici. Provjereni izvorni obrasci; prijave nisu slane.
Galerija, ALERRT i CRASE: dodan pregled fotografija unutar stranice, strelice, tipkovnica i zatvaranje tipkom Escape. Galerija provjerena s 132 fotografije.

Provjera: svih 36 ruta vraća 200, provjereni izvori fotografija i SQL filtri. Obrasci nisu slani. Galerija pregledana u browseru. Preostaje vizualna provjera svake stranice i puna provjera vanjskih dokumenata/servisa prije objave. Generički prijenos sadržaja još nije završna dizajnerska dorada svih rubrika.

Projekti: 12 projektnih stranica i projektni tim, HR/EN objave iz baze bez dupliranja ID-a, izvorne poveznice i dokumenti. Svi izvorni /Projekt/*.aspx URL-ovi provjereni: 301 prema odgovarajućoj PHP ruti. Generiranje: php migration/extract-projects.php.
Bočna navigacija: povezane teme prema trenutnoj stranici; sadržaj samo uz najmanje dvije korisne sekcije. Na mobitelu ispod glavnog sadržaja.

Alumni: vraćena izravna Cognito iframe ugradnja s izvornim obrascem 1 i iframe.js za prilagodbu visine; rezervni link ostaje. Lokalni prikaz potrebno potvrditi u korisnikovom pregledniku; slanje nije testirano.
