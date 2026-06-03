# `db.php` — Připojení k databázi

Zdroj: [../../db.php](../../db.php) (21 řádků)

Tento modul má **jediný úkol**: vytvořit objekt `$pdo`, přes který zbytek aplikace komunikuje
s databází MariaDB. Žádný HTML výstup, žádná logika — jen příprava spojení. Připojuje se přes
`require_once 'db.php';` (viz [api.md](api.md)).

## Kompletní rozbor řádek po řádku

```php
<?php
$db_host = getenv('DB_HOST') ?: 'db';
$db_name = getenv('DB_NAME') ?: 'cryptodb';
$db_user = getenv('DB_USER') ?: 'crypto';
$db_pass = getenv('DB_PASS') ?: 'crypto_pass';
```

### `<?php`
Otevírací značka PHP. Všechno za ní je PHP kód až do konce souboru (zde žádná uzavírací `?>`
není — to je **úmyslné a doporučené** u čistě PHP souborů, aby na konec nepronikly náhodné
mezery/odřádkování, které by se poslaly do výstupu).

### `getenv('DB_HOST') ?: 'db'`
- `getenv('DB_HOST')` přečte **proměnnou prostředí** `DB_HOST`. Tyto proměnné nastavuje Docker
  v [docker-compose.yml](../docker/docker-compose.md) v sekci `environment:`.
- `?:` je tzv. **Elvis operátor** (zkrácený ternární). `A ?: B` znamená „když je `A` pravdivé
  (truthy), použij `A`, jinak `B`". Pokud proměnná prostředí neexistuje (`getenv` vrátí `false`),
  použije se výchozí hodnota — zde `'db'`.

Proč tento vzor? **Konfigurovatelnost.** V Dockeru přijdou hodnoty z prostředí. Když bys soubor
spustil mimo Docker bez proměnných, použijí se rozumné defaulty. Hodnoty `'db'`, `'cryptodb'`,
`'crypto'`, `'crypto_pass'` přesně odpovídají tomu, co je nastavené v compose souboru.

> **Pozor**: `'db'` jako host není localhost! Je to **jméno služby** z docker-compose. Docker má
> interní DNS, kde se kontejner databáze jmenuje `db`. Více viz [docker-compose.md](../docker/docker-compose.md).

```php
$dsn = 'mysql:host=' . $db_host . ';dbname=' . $db_name;
```

### DSN (Data Source Name)
Řetězec, který PDO říká **jak a kam** se připojit. Formát pro MySQL/MariaDB:
```
mysql:host=<host>;dbname=<jméno_databáze>
```
- `.` je v PHP operátor **spojení řetězců** (konkatenace) — pozor, ne `+` jako v JS!
- Výsledek při defaultech: `mysql:host=db;dbname=cryptodb`

Prefix `mysql:` říká PDO, který **driver** použít. MariaDB je kompatibilní s MySQL protokolem,
proto se používá `mysql` driver i pro MariaDB.

```php
// Retry dokud DB není připravena (race condition: web startuje dřív než MariaDB)
$pdo = null;
for ($i = 0; $i < 10; $i++) {
    try {
        $pdo = new PDO($dsn, $db_user, $db_pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        break;
    } catch (PDOException $e) {
        if ($i === 9) throw $e;  // po 10 pokusech vzdej a vyhoď výjimku
        sleep(2);
    }
}
```

### Proč retry smyčka?
**Problém časování (race condition).** Když `docker compose up` nastartuje oba kontejnery,
webový server (Apache+PHP) může být připraven dřív, než MariaDB dokončí inicializaci. První
pokus o připojení by selhal výjimkou. Tato smyčka to řeší: zkusí se připojit až **10×**,
mezi pokusy čeká **2 sekundy** (`sleep(2)`), celkem tedy až ~20 sekund tolerance.

> Pozn.: compose soubor navíc používá `depends_on: condition: service_healthy` (viz
> [docker-compose.md](../docker/docker-compose.md)), takže web obvykle nastartuje až po
> health-checku DB. Retry je **druhá pojistka** pro případ, že DB ohlásí „healthy", ale ještě
> nestihne přijmout spojení.

### `new PDO($dsn, $db_user, $db_pass)`
**PDO** = PHP Data Objects — vestavěná abstrakce pro práci s databázemi. Konstruktor naváže
spojení. Pokud se nepovede, vyhodí `PDOException`.

### `setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION)`
Velmi důležité nastavení. Říká PDO: **„při SQL chybě vyhoď výjimku"** místo tichého selhání.
Bez něj by chybný dotaz jen vrátil `false` a chyba by se snadno přehlédla. S tímto nastavením
ji zachytí `try/catch` v [api.php](api.md).

### `break;`
Při úspěšném připojení ukončí `for` smyčku — nemá smysl zkoušet dál.

### `catch (PDOException $e)`
Zachytí selhání připojení.
- `if ($i === 9) throw $e;` — pokud to byl **poslední** (desátý, index 9) pokus, výjimku
  „přehoď dál" (re-throw). Tu pak zachytí volající kód v `api.php` a pošle uživateli JSON chybu.
- `sleep(2);` — jinak počkej 2 s a zkus znova.

## Jak modul měnit

| Chci… | Udělej… |
|-------|---------|
| Připojit jinou DB | Změň proměnné prostředí v [docker-compose.yml](../docker/docker-compose.md), ne tento soubor |
| Více/méně pokusů | Uprav `$i < 10` a podmínku `$i === 9` (musí být `počet - 1`) |
| Delší čekání | Změň `sleep(2)` |
| Vynutit UTF-8 | Přidej `;charset=utf8mb4` do `$dsn` |
| Zakázat emulaci prepared statements | `$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);` |

## Bezpečnostní poznámka
Heslo `crypto_pass` je v plaintextu jako default. Pro reálné nasazení by hesla měla jít **výhradně**
přes proměnné prostředí / secret management, nikdy ne jako hardcoded default. Pro edukační projekt
v izolované Docker síti je to akceptovatelné.

## Související
- [api.md](api.md) — používá `$pdo` pro dotazy
- [../docker/init-sql.md](../docker/init-sql.md) — schéma tabulky, do které se zapisuje
- [../zaklady-technologii.md](../zaklady-technologii.md#sql-a-mariadb) — co je SQL/PDO
