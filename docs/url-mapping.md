# URL starého e-shopu → nový e-shop

Cíl: všechny veřejné URL zůstanou 1:1. Co se změnit musí, dostane 301 přes správu přesměrování.

| Stránka | Starý e-shop | Nový e-shop | Řešení |
|---|---|---|---|
| Úvod | `/` → `/cs_CZ/` | stejné | Sylius locale switch |
| Detail produktu | `/cs_CZ/products/{slug}` | stejné | Sylius route; slug se při migraci převezme ze staré DB, sync ho po založení nemění |
| Výpis kategorie | `/cs_CZ/products-list/{slug}` | stejné | vlastní route na controller BitBag ES 5.3 (ten má výchozí `/taxons/{slug}`) |
| Kategorie Sylius | `/cs_CZ/taxons/{slug}` → 301 na products-list | stejné | redirect route jako ve starém `config/routes/sylius_shop.yaml` |
| Vyhledávání | `/cs_CZ/search` | stejné | BitBag ES |
| Našeptávač | `/cs_CZ/auto-complete/product`, `/cs_CZ/auto-complete/product-all` | stejné | BitBag ES + vlastní akce (hledání i v obj. číslech) |
| CMS stránka | `/cs_CZ/page/{slug}` | stejné | sylius/cms-plugin, slugy z migrace BitBag CMS |
| Kontakt | `/cs_CZ/contact` | stejné | vlastní kontaktní formulář |
| Registrace | `/cs_CZ/register` → 301 `/cs_CZ/registration-request` | stejné | žádost o registraci |
| Oblíbené | `/wishlist`, `/wishlist/*` | stejné | port wishlist pluginu |
| Obchodní zástupce | `/cs_CZ/sale`, `/set-organization/{id}`, `/set-organization-customer/{id}` | stejné | port funkce zástupců |
| Účet | `/cs_CZ/account/*` | stejné | Sylius |
| Košík, pokladna | `/cs_CZ/cart`, `/cs_CZ/checkout/*`, `/select-shipping-payment` | Sylius 2 kroky | mimo SEO, bez redirectu; sloučený krok doprava + platba se zachová |
| Média | `/media/image/...`, `/media/cache/...` | stejné cesty | obrázky a dokumenty se přenesou se stejnou relativní cestou |

## Slugy

- Kategorie: kód = transliterovaný název (`frezovani`), slug = kód s `-` (`frezovani`, `frezovani-s-vbd`). Synchronizace z Heliosu kód a slug nastaví jen při založení.
- Produkty: slug ze Sylius slug generátoru z názvu, při kolizi `-{helios id}`. Nastaví se jen při založení; existující produkty dostanou slug ze staré DB.
- Po migraci se porovná seznam URL ze staré DB (produkty, kategorie, CMS) s novou; rozdíly půjdou jako 301 do správy přesměrování.
