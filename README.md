# E-shop SeMaCo

Nový e-shop eshop.semaco.cz na Sylius 2.2 / Symfony 7.4 / PHP 8.5. Nahrazuje starý
projekt `../semaco` (Sylius 1.9) — funkčně a vzhledově 1:1.

## Lokální prostředí

```bash
docker compose up -d
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate -n
docker compose exec php php bin/console sylius:fixtures:load semaco -n
docker compose exec php yarn install
docker compose exec php yarn build
```

| Služba | Adresa |
|---|---|
| Shop | http://localhost:8095 |
| Admin | http://localhost:8095/admin (vývojový účet ve fixtures `config/packages/sylius_fixtures.yaml`) |
| MySQL | 127.0.0.1:3309 (root / semaco) |
| Mailpit | http://localhost:8029 |
| Adminer | http://localhost:8089 |

Síť projektu má pevnou podsíť `172.16.95.0/24` (výchozí pooly Dockeru jsou vyčerpané).

## Helios

Přímé MSSQL (`pdo_sqlsrv`). Server je dostupný jen přes SSH tunel, viz `HELIOS_*`
v `.env` a lokální hodnoty v `.env.local`. Do Heliosu se zatím jen čte.
