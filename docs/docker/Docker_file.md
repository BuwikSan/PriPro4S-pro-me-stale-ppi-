# Dockerfile — recept na webový image

Zdroj: [../../Dockerfile](../../Dockerfile) (20 řádků)

`Dockerfile` je **recept**, podle kterého Docker sestaví („build") obraz (image) webového
kontejneru. Obraz je neměnná šablona; z ní pak běží kontejner. Tento konkrétní obraz obsahuje:
PHP 8.2 + Apache web server + Python 3 (s numpy a sympy) + PHP rozšíření pro MySQL.

> **Image vs. kontejner**: image = „zmražená" šablona (jako třída). Kontejner = běžící instance
> image (jako objekt). Z jednoho image lze spustit více kontejnerů.

## Rozbor řádek po řádku

```dockerfile
FROM php:8.2-apache
```
### `FROM` — základní image
Každý Dockerfile staví **na něčem**. `php:8.2-apache` je oficiální image z Docker Hubu, který
už obsahuje PHP 8.2 a předkonfigurovaný Apache web server. Tag `8.2-apache` říká „verze PHP 8.2,
varianta s Apache". Díky tomu nemusíš Apache ani PHP instalovat ručně.

```dockerfile
# Install all packages in one layer to reduce size
RUN apt-get update && apt-get install -y \
  python3 \
  python3-numpy \
  python3-sympy \
  && rm -rf /var/lib/apt/lists/*
```
### `RUN` — spuštění příkazu při buildu
`RUN` spustí shell příkaz **během sestavování** image. Základní image je Debian, takže používá
správce balíčků `apt-get`.

- `apt-get update` — stáhne aktuální seznam balíčků.
- `apt-get install -y python3 python3-numpy python3-sympy` — nainstaluje **Python 3** a knihovny
**numpy** (matice — pro Hill šifru) a **sympy** (modulární inverze — pro Hill). `-y` =
automaticky odpověz „ano" na dotazy (build je neinteraktivní).
- `rm -rf /var/lib/apt/lists/*` — smaže cache balíčků → **menší výsledný image**.

#### Proč všechno v jednom `RUN`?
Komentář „Install all packages in one layer" je klíčový. Každý `RUN` vytvoří **vrstvu (layer)**
v image. Kdyby byl `apt-get update` a `install` ve dvou příkazech, cache seznamu by zůstala v
mezivrstvě a zbytečně nafoukla image. Spojení přes `&&` do jednoho `RUN` = jedna vrstva, menší
image. To je standardní Docker best-practice.

> Python se sem instaluje, protože [api.php](../php/api.md#část-4-callpython--most-mezi-php-a-pythonem-řádky-102118)
> ho spouští přes `proc_open('python3 ...')`. PHP a Python tedy běží ve **stejném kontejneru**.

```dockerfile
# PHP extensions
RUN docker-php-ext-install pdo pdo_mysql
```
### Instalace PHP rozšíření
`docker-php-ext-install` je pomocný skript dodaný v oficiálním PHP image. Nainstaluje a zapne
PHP rozšíření:
- `pdo` — obecná vrstva PDO (PHP Data Objects).
- `pdo_mysql` — konkrétní driver pro MySQL/MariaDB.

**Bez tohoto řádku by `new PDO('mysql:...')` v [db.php](../php/db.md) selhalo** — driver by chyběl.
To je přesné propojení: db.php potřebuje `pdo_mysql`, a tady se instaluje.

```dockerfile
# Apache config
RUN a2enmod rewrite
RUN echo 'short_open_tag = Off' >> /usr/local/etc/php/php.ini
```
### Konfigurace Apache a PHP
- `a2enmod rewrite` — („**a2enmod**" = Apache2 enable module) zapne modul `mod_rewrite` pro
přepisování URL. V tomto projektu se aktivně nevyužívá, ale je to běžná příprava.
- `echo 'short_open_tag = Off' >> .../php.ini` — připíše do PHP konfigurace vypnutí „krátkých
značek". To znamená, že PHP rozpozná **jen** `<?php`, ne `<?`. Brání záměnám a je to bezpečnější
default. (`>>` = připojit na konec souboru.)

```dockerfile
WORKDIR /var/www/html
```
### `WORKDIR` — pracovní adresář
Nastaví výchozí adresář uvnitř kontejneru na `/var/www/html` — to je standardní kořen webu pro
Apache. Sem se přes volume namountuje tvůj kód (viz [docker-compose.md](docker-compose.md)).
Proto `api.php` používá absolutní cestu `/var/www/html/cipher_wrapper.py`.

```dockerfile
EXPOSE 80
```
### `EXPOSE` — dokumentace portu
Říká, že kontejner naslouchá na portu **80** (HTTP). `EXPOSE` je hlavně **dokumentační** — port
samotný zpřístupní až `ports:` v compose souboru. Apache uvnitř kontejneru poslouchá na 80.

## Co tu chybí a proč to (zatím) funguje
- **Žádné `COPY`/`ADD`**: kód se do image nekopíruje! Místo toho ho compose **mountuje** jako
volume (`.:/var/www/html`). Výhoda při vývoji: změníš PHP soubor a hned je vidět, bez rebuildu.
Nevýhoda pro produkci: image není soběstačný (potřebuje zdrojové soubory zvenčí).
- **Žádný `CMD`/`ENTRYPOINT`**: dědí se ze základního image `php:8.2-apache`, který sám spouští
Apache na popředí.

## Build a běh
```bash
docker compose build        # sestaví image podle tohoto Dockerfile
docker compose up           # spustí (a v případě potřeby sestaví)
```

## Jak Dockerfile měnit

| Chci… | Udělej… |
|-------|---------|
| Jinou verzi PHP | Změň `FROM php:8.3-apache` apod. |
| Přidat PHP rozšíření | `RUN docker-php-ext-install <jméno>` (např. `gd`, `mbstring`) |
| Přidat systémový balíček | Přidej do `apt-get install` seznamu (drž v jednom `RUN`) |
| Kód zabudovat do image (produkce) | Přidej `COPY . /var/www/html` a v compose zruš volume mount |
| Vlastní php.ini hodnoty | Další `RUN echo '...' >> /usr/local/etc/php/php.ini` |

## Související
- [docker-compose.md](docker-compose.md) — jak se z tohoto image spustí kontejner a propojí s DB
- [../php/db.md](../php/db.md) — využívá `pdo_mysql` instalované zde
- [../zaklady-technologii.md](../zaklady-technologii.md#docker) — co je Docker, image, vrstvy
