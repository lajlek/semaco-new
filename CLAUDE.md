# SeMaCo e-shop — projektová pravidla

## Reference
- Funkčnost a vzhled 1:1 se starým e-shopem https://eshop.semaco.cz/cs_CZ/ (kód `../semaco`,
  Sylius 1.9 + `vendor/mergocz/sylius-helios-plugin`). Chování NEW ≠ OLD je bug; chyby OLD
  se nekopírují, opravují.
- Rozsah a funkce: `../semaco/nabidka/finalni/semaco-nabidka.md`, ceníky a firemní funkce:
  `../semaco/nabidka/06-overeni-ceniku-a-firemnich-funkci.md`.
- ERP vrstva je port z `../otavan/src/Erp` (porty/adaptéry, outbox, inkrementální sync).
  Otavan specifika (zaměstnanci, sady, schvalování, PunchOut, předloha) sem nepatří.
- Šablona: Riode (d-themes) `demo1` + úpravy SeMaCo (`public/bootstrap-theme/build/css/*` ve starém
  projektu). URL zůstávají 1:1 (`/cs_CZ/products/{slug}`, `/cs_CZ/products-list/{slug}`), změny přes redirecty.

## Architektura
- Application vrstva (`src/Erp/Application`) nezná Helios: žádné `regcis`, `cenovauroven`, ID úrovní
  ani natvrdo ID platebních metod mimo `src/Erp/Adapter/Helios`.
- Repository jen přes constructor DI a jen ve service vrstvě; žádné `getRepository()`.
- Žádné úpravy `vendor/`; customizace přes dekorátory, Twig hooky, form extensions, resource config.
- Nové entity jako Sylius resources; schéma jen migracemi.

## Helios: jen čtení
Do Heliosu se nezapisuje, dokud to Miloš výslovně nepovolí (cutover). Zápisové porty existují,
zápisy jdou do fronty `erp_push_hold`.
