# `docker-compose.yml` — orchestrace kontejnerů

Zdroj: [../../docker-compose.yml](../../docker-compose.yml) (45 řádků)

Zatímco [Dockerfile](Dockerfile.md) popisuje **jeden** image, `docker-compose.yml` popisuje
**celou aplikaci** — kolik kontejnerů běží, jak jsou propojené, jaké mají proměnné, porty a
úložiště. Spustíš to jediným příkazem:

```bash
docker compose up        # postaví + spustí vše
docker compose down      # zastaví a smaže kontejnery (data v named volume zůstanou)
docker compose up -d     # běh na pozadí (detached)
```

Aplikace má **dvě služby (kontejnery)**: `web` (PHP+Apache+Python) a `db` (MariaDB).

> Soubor je v jazyce **YAML** — odsazení (mezerami!) definuje strukturu. Tab je zakázaný.

## Rozbor po sekcích

```yaml
services:
  web:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
    environment:
      DB_HOST: db
      DB_USER: crypto
      DB_PASS: crypto_pass
      DB_NAME: cryptodb
    depends_on:
      db:
        condition: service_healthy
    networks:
      - crypto_net
```

### Služba `web`
- **`build:`** — tato služba se nestaví z hotového image, ale **sestaví se** podle Dockerfile.
  - `context: .` — build kontext je aktuální adresář (odsud se berou soubory).
  - `dockerfile: Dockerfile` — který recept použít.
- **`ports: - "80:80"`** — mapování portů `HOST:KONTEJNER`. Port 80 na tvém počítači →
  port 80 v kontejneru (kde poslouchá Apache, viz `EXPOSE 80`). Proto otevřeš `http://localhost`.
- **`volumes: - .:/var/www/html`** — **bind mount**: aktuální adresář (`.`) se „napojí" do
  `/var/www/html` v kontejneru. Tvůj PHP/JS/CSS kód je tak živě dostupný — změna souboru se
  projeví **bez rebuildu**. (Proto Dockerfile nepotřebuje `COPY`.)
- **`environment:`** — proměnné prostředí předané do kontejneru. Přesně tyto čte
  [db.php](../php/db.md) přes `getenv('DB_HOST')` atd. **Hodnota `DB_HOST: db` je jméno druhé
  služby** — Docker DNS ji přeloží na IP kontejneru databáze. To je důvod, proč v db.php není
  `localhost`.
- **`depends_on: db: condition: service_healthy`** — `web` se spustí až **poté**, co `db` projde
  health-checkem (viz níže). Bez toho by se web pokoušel připojit k neexistující DB. (Retry
  smyčka v db.php je druhá pojistka.)
- **`networks: - crypto_net`** — připojí službu do sdílené sítě (viz dole).

```yaml
  db:
    image: mariadb:11
    environment:
      MYSQL_ROOT_PASSWORD: root
      MYSQL_DATABASE: cryptodb
      MYSQL_USER: crypto
      MYSQL_PASSWORD: crypto_pass
    volumes:
      - ./init.sql:/docker-entrypoint-initdb.d/init.sql
      - db_data:/var/lib/mysql
    networks:
      - crypto_net
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 10
      start_period: 10s
```

### Služba `db`
- **`image: mariadb:11`** — použije **hotový** image MariaDB 11 z Docker Hubu (žádný build).
- **`environment:`** — speciální proměnné, kterým rozumí MariaDB image **při prvním startu**:
  - `MYSQL_ROOT_PASSWORD: root` — heslo administrátora.
  - `MYSQL_DATABASE: cryptodb` — vytvoří databázi tohoto jména.
  - `MYSQL_USER` / `MYSQL_PASSWORD` — vytvoří běžného uživatele `crypto` s heslem. Tyto hodnoty
    **musí sedět** s `DB_USER`/`DB_PASS` u služby `web`, jinak by se web nepřipojil.
- **`volumes:`** — dva typy:
  - `./init.sql:/docker-entrypoint-initdb.d/init.sql` — bind mount. Cokoli v adresáři
    `/docker-entrypoint-initdb.d/` MariaDB **automaticky spustí při první inicializaci**.
    Tím se vytvoří tabulka `history` (viz [init-sql.md](init-sql.md)).
  - `db_data:/var/lib/mysql` — **named volume** (pojmenovaný svazek). Sem MariaDB ukládá data.
    Díky tomu data **přežijí** smazání kontejneru (`docker compose down`). Bez něj bys o historii
    přišel při každém restartu.
- **`healthcheck:`** — jak Docker pozná, že DB je „zdravá"/připravená:
  - `test: [...]` — příkaz, který se spouští uvnitř kontejneru; `healthcheck.sh --connect
    --innodb_initialized` ověří, že MariaDB přijímá spojení a má inicializovaný InnoDB.
  - `interval: 5s` — testuj každých 5 s.
  - `timeout: 5s` — jeden test smí trvat max 5 s.
  - `retries: 10` — po 10 neúspěšných testech označ jako „unhealthy".
  - `start_period: 10s` — prvních 10 s neúspěchy nepočítej (DB se rozjíždí).
  - Na tento health-check čeká `depends_on` u služby `web`.

```yaml
volumes:
  db_data:

networks:
  crypto_net:
```

### Globální `volumes` a `networks`
- **`volumes: db_data:`** — deklarace named volume použitého výše. Docker ho spravuje sám
  (uloženo mimo projektový adresář). Smažeš ho jen explicitně (`docker compose down -v`).
- **`networks: crypto_net:`** — deklarace privátní sítě. Obě služby jsou v ní → vidí se navzájem
  podle jména služby (`web` ↔ `db`). Izoluje aplikaci od ostatních Docker projektů.

## Jak to celé do sebe zapadá

```
docker compose up
   │
   ├─ vytvoří síť crypto_net + volume db_data
   │
   ├─ spustí kontejner db (mariadb:11)
   │     ├─ poprvé: vytvoří databázi cryptodb, uživatele crypto, spustí init.sql
   │     └─ healthcheck každých 5 s → až "healthy"
   │
   └─ poté (depends_on) spustí kontejner web
         ├─ namountuje tvůj kód do /var/www/html
         ├─ Apache poslouchá na 80 → dostupné na http://localhost
         └─ db.php se připojí k host "db" pomocí env proměnných
```

## Jak compose měnit

| Chci… | Udělej… |
|-------|---------|
| Změnit port (kolize s 80) | `ports: - "8080:80"` → aplikace na `http://localhost:8080` |
| Změnit DB hesla | Uprav `environment` u `db` **i** `web` zároveň (musí sedět) |
| Smazat a začít s prázdnou DB | `docker compose down -v` (smaže i `db_data`) |
| Jinou verzi DB | `image: mariadb:11` → jiná verze |
| Přidat službu (např. Redis) | Přidej další položku pod `services:` + do `networks` |
| Produkční režim | Zruš bind mount kódu, přidej `COPY` do Dockerfile |

> **Pozn.**: `init.sql` se spustí **jen při prvním** vytvoření volume `db_data`. Když změníš
> `init.sql` a chceš ho aplikovat, musíš volume smazat: `docker compose down -v && docker compose up`.

## Související
- [Dockerfile.md](Dockerfile.md) — image pro službu `web`
- [init-sql.md](init-sql.md) — skript spuštěný službou `db` při inicializaci
- [../php/db.md](../php/db.md) — čte `environment` proměnné odsud
- [../zaklady-technologii.md](../zaklady-technologii.md#docker) — Docker, YAML, sítě, volumes
