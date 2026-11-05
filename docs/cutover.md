# Spuštění nového e-shopu (cutover)

Postup přechodu ze starého e-shopu (Sylius 1.9) na nový. Kroky jdou v tomto pořadí.

## Před spuštěním

- Server: PHP 8.5, MySQL 8.4, Node pro sestavení assetů. Proměnné prostředí podle `.env`
  (`DATABASE_URL`, `OLD_SHOP_DATABASE_URL`, `HELIOS_*`, `MAILER_DSN`, `GOOGLE_RECAPTCHA_*`, `LOCK_DSN`).
- Helios: přístup ze serveru (tunel nebo whitelist IP), databáze podle rozhodnutí (`SEMACO` ostrá, ne kopie `SEMACO1`).
- Zápis do Heliosu zůstává vypnutý: `HELIOS_WRITE_ENABLED=0`, `ERP_ORDER_EXPORT_SINCE` v budoucnosti.
  Zapnout až po výslovném souhlasu.
- `composer install --no-dev`, `yarn install && yarn build`, `bin/console doctrine:migrations:migrate`,
  `bin/console cache:clear`.
- První úplná synchronizace katalogu a firem: `bin/console app:erp:sync --no-debug`. Kategorie podle výrobců vzniknou
  při ní a každá další synchronizace do nich zařadí nové produkty.

## Přepnutí

1. Zastavit objednávání na starém e-shopu (údržbová stránka).
2. Čerstvý dump staré databáze do databáze z `OLD_SHOP_DATABASE_URL`.
3. `bin/console app:migrate:old` (všechny kroky: slugy, data založení produktů, přesměrování, zákazníci, admini, CMS,
   poptávky, wishlisty, číslování objednávek). Nejdřív lze pustit `--dry-run`.
4. Obrázky a dokumenty: kopie starého `public/media` (jen čtení, rsync) a `bin/console app:migrate:media --source=…`.
   Doplní jen chybějící soubory. Dump i kopii souborů vzít ve stejnou chvíli – starý web obrázky při změně v Heliosu
   nahrával s novou cestou, takže starší dump odkazuje na soubory, které už neexistují.
   Nový web obrázky z Heliosu nestahuje (podle nabídky se po převzetí spravují v e-shopu); produkty, které starý web
   neměl, dostanou obrázky v administraci.
5. Tři PDF ze starého `public/files` do `public/files` (stejné URL): `H8604_e.pdf`, `P15257_e.pdf`,
   `rezne_parametry.pdf`.
6. Přepnout DNS / virtual host na nový web.
7. `bin/console app:migrate:check-urls --base-url=https://eshop.semaco.cz` – každá veřejná URL starého webu musí vrátit
   200 nebo přesměrovat na 200. Chybějící doplnit jako 301 ve správě přesměrování.

## Workery

Dva samostatné procesy (supervisor / systemd), aby načítání partnerů nezdržovalo synchronizaci s Heliosem:

- `bin/console messenger:consume scheduler_default --time-limit=3600` – synchronizace s Heliosem (10 min), export
  objednávek (2 min), kurz ČNB (pracovní dny 14:50).
- `bin/console messenger:consume scheduler_partners --time-limit=3600` – načítání partnerů zadané v administraci
  (fronta, každou minutu) a párování exportovaných položek (každou hodinu).

Oba workery a web musí sdílet `cache.app` a zámky (`LOCK_DSN`) – při jednom serveru stačí výchozí souborové úložiště.
Po nasazení nové verze workery restartovat (`messenger:stop-workers`).

## Po spuštění

- Admin: přihlášení převedených administrátorů, objednávka zkušebním účtem, e-maily (potvrzení, reset hesla).
- Partneři: v administraci Katalog → Partnerský sortiment. Sledované kategorie se vybírají zaškrtnutím, načítání spustí
  tlačítko a pokračuje plánovač (nejvýš 30 stránek za hodinu, odstup ≥ 10 s, robots.txt, po odmítnutí 6 h pauza).
  BRW se bere jen z řad FUTURO a NERIOX.
