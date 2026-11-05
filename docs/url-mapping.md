# URL starého e-shopu → nový e-shop

Cíl: všechny veřejné URL zůstanou 1:1. Co se změnit musí, dostane 301 přes správu přesměrování.

| Stránka | Starý e-shop | Nový e-shop | Řešení |
|---|---|---|---|
| Úvod | `/` → `/cs_CZ/` | stejné | Sylius locale switch |
| Detail produktu | `/cs_CZ/products/{slug}` | stejné | Sylius route; slug se při migraci převezme ze staré DB, sync ho po založení nemění |
| Výpis kategorie | `/cs_CZ/products-list/{slug}` | stejné | vlastní route na výpis produktů Sylia |
| Kategorie Sylius | `/cs_CZ/taxons/{slug}` → 301 na products-list | stejné | redirect route jako ve starém `config/routes/sylius_shop.yaml` |
| Kategorie výrobce | není | `/cs_CZ/products-list/pokolm`, `/cs_CZ/products-list/fraisa/stopkove-frezy/ax` | nové menu; staré kategorie zůstávají dostupné na svých adresách, jen nejsou v menu |
| Vyhledávání | `/cs_CZ/search` | stejné | vyhledávání v MySQL, přijímá i starý parametr `bitbag_elasticsearch_search[box][query]` |
| Našeptávač | `/cs_CZ/auto-complete/product`, `/cs_CZ/auto-complete/product-all` | stejné | vlastní akce (hledání i v obj. číslech) |
| Značka | `/cs_CZ/brand/{code}` (jen název značky, nikde neodkazovaná) | 301 na úvod | redirect route |
| CMS stránka | `/cs_CZ/page/{slug}` | stejné | vlastní CMS (`src/Cms`), slugy z migrace BitBag CMS; staré `/cs_CZ/terms/*` 301 na CMS |
| Kontakt | `/cs_CZ/contact` | stejné | vlastní kontaktní formulář |
| Registrace | `/cs_CZ/register` → 301 `/cs_CZ/registration-request` | stejné | žádost o registraci |
| Oblíbené | `/wishlist`, `/wishlist/*` | stejné | port wishlist pluginu |
| Obchodní zástupce | `/cs_CZ/sale`, `/set-organization/{id}`, `/set-organization-customer/{id}` | stejné | port funkce zástupců |
| Účet | `/cs_CZ/account/*` | stejné | Sylius |
| Košík, pokladna | `/cs_CZ/cart`, `/cs_CZ/checkout/*`, `/select-shipping-payment` | `/cs_CZ/checkout/select-shipping-payment` | sloučený krok doprava + platba; staré `select-shipping`/`select-payment` 302 na něj |
| Média | `/media/image/...`, `/media/cache/...` | stejné cesty | obrázky a dokumenty se přenesou se stejnou relativní cestou |

## Slugy

- Kategorie: kód = transliterovaný název (`frezovani`), slug = kód s `-` (`frezovani`, `frezovani-s-vbd`). Synchronizace z Heliosu kód a slug nastaví jen při založení.
- Produkty: slug ze Sylius slug generátoru z názvu, při kolizi `-{helios id}`. Nastaví se jen při založení; existující produkty dostanou slug ze staré DB.
- Po migraci ověří `bin/console app:migrate:check-urls --base-url=https://…` všechny veřejné URL ze staré DB (produkty,
  kategorie, CMS, značky, statické stránky): každá musí vrátit 200 nebo přesměrovat na 200. Chybějící doplnit jako 301
  ve správě přesměrování.
