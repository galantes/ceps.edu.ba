# SEO adrese i kompatibilnost

Javna PHP aplikacija koristi male znakove i crtice, bez .aspx nastavka.

- `/StudijIT.aspx` → `/studiji/informacijske-tehnologije`
- `/Medunarodna_suradnja` → `/medunarodna-suradnja`
- `/Cisco` → `/cisco-akademija`
- `/Novost.aspx?id=1` → `/novosti/1-studenti-ceps-a-posjetili-sarajevski-kiseljak`
- `/Projekt/ncp_widera_net` → `/projekti/ncp-widera-net`

Stare adrese, različita velika/mala slova i završna kosa crta trajno se preusmjeravaju (301). ID u adresi novosti čuva identitet objave; stari naslov u slugu preusmjerava se na aktualni. Parametri i sidra ostaju dostupni. Linkovi prema dokumentima zadržavaju postojeću obradu.

`src/seo.php` je centralna mapa. Renderirane poveznice, forme i canonical oznake koriste nove adrese. Kontakt POST se obrađuje izravno kako se poruka ne bi izgubila preusmjeravanjem.

Za objavu je potreban postojeći Apache rewrite iz `public/.htaccess`, uz `public` kao document root. Ne kopirati stare ASPX datoteke u taj direktorij jer fizičke datoteke zaobilaze router. Ove promjene su u lokalnoj PHP aplikaciji; nisu automatski objavljene na starom produkcijskom webu. Preview robots/noindex postavke ostaju na snazi do zasebne objave.

Provjera: `python php-app/tests/http_check.py` uz lokalni server na portu 8086. Obuhvaća sve novosti, stare adrese, canonical oznake, arhivu, statičke podstranice, stare dokumente, 404 i POST ponašanje.
