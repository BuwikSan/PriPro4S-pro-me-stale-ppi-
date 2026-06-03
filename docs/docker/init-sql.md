# `init.sql` — schéma databáze

Zdroj: [../../init.sql](../../init.sql) (12 řádků)

Tento SQL skript se spustí **jednou**, při první inicializaci databáze. MariaDB image automaticky
spouští vše v `/docker-entrypoint-initdb.d/`, kam je `init.sql` namountován (viz
[docker-compose.md](docker-compose.md)). Vytváří jedinou tabulku `history`, do které
[api.php](../php/api.md) zapisuje a čte celou historii operací.

## Kompletní rozbor

```sql
CREATE TABLE IF NOT EXISTS history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    cipher_type VARCHAR(20)  NOT NULL,
    typ_operace VARCHAR(10)  NOT NULL,
    cipher_key  TEXT,
    input       TEXT,
    output      TEXT,
    parent_id   INT          NULL,                   -- dec záznam odkazuje na svůj enc rodič
    timestamp   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cipher_type (cipher_type),
    INDEX idx_parent_id   (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### `CREATE TABLE IF NOT EXISTS history`
Vytvoří tabulku `history`. `IF NOT EXISTS` = nevyhazuj chybu, když už existuje (skript je
**idempotentní** — bezpečně spustitelný vícekrát).

### Sloupce (definice struktury)

| Sloupec | Typ | Poznámka |
|---------|-----|----------|
| `id` | `INT AUTO_INCREMENT PRIMARY KEY` | Unikátní číslo řádku, samo se zvyšuje. Primární klíč. |
| `cipher_type` | `VARCHAR(20) NOT NULL` | `'hill'` / `'mlkem'`. Filtr v GET dotazu. `NOT NULL` = povinné. |
| `typ_operace` | `VARCHAR(10) NOT NULL` | `'enc'` / `'dec'`. Rozlišuje šifrování/dešifrování. |
| `cipher_key` | `TEXT` | Klíč(e) k dešifrování, uložené jako JSON. U dec záznamů `NULL`. |
| `input` | `TEXT` | Vstupní text operace. |
| `output` | `TEXT` | Výsledek operace (ciphertext nebo plaintext). |
| `parent_id` | `INT NULL` | U dec záznamu = `id` jeho enc rodiče. U enc záznamu `NULL`. |
| `timestamp` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | Čas vytvoření — doplní se sám. |

#### Detaily typů
- **`INT AUTO_INCREMENT`** — celé číslo, DB ho při každém `INSERT` automaticky zvýší (1, 2, 3, …).
  Proto `logHistory()` v api.php `id` nevkládá.
- **`VARCHAR(n)`** — textový řetězec o **proměnné** délce, max `n` znaků. Vhodné pro krátké hodnoty.
- **`TEXT`** — dlouhý text (až ~64 KB). Vhodné pro vstup/výstup/klíče, které můžou být velké.
- **`PRIMARY KEY`** — jednoznačná identifikace řádku; automaticky vytvoří index a vynutí unikátnost.
- **`NOT NULL`** — hodnota je povinná (vložení bez ní selže).
- **`NULL`** (u `parent_id`) — hodnota smí chybět; právě tím se pozná enc záznam (`parent_id IS NULL`).
- **`DEFAULT CURRENT_TIMESTAMP`** — když se hodnota nevloží, DB doplní aktuální čas serveru.

### Vztah parent–child (rodič–dítě)
Sloupec `parent_id` realizuje **stromovou vazbu** uvnitř jedné tabulky (self-reference):
- **enc** záznam: `parent_id = NULL` (je sám sobě kořenem).
- **dec** záznam: `parent_id = id enc rodiče` (odkazuje na šifrování, které dešifruje).

Toho využívá řazení v [api.php](../php/api.md#část-2-get--čtení-historie-řádky-1932):
`ORDER BY COALESCE(parent_id, id) DESC, id ASC` — díky tomu se dec záznam v tabulce zobrazí
hned pod svým enc rodičem. Vizuální odsazení zařídí CSS třída `dec-row`.

### Indexy
```sql
INDEX idx_cipher_type (cipher_type),
INDEX idx_parent_id   (parent_id)
```
**Index** = pomocná datová struktura, která zrychluje vyhledávání podle daného sloupce (jako
rejstřík v knize). Vytvořené proto, že api.php často filtruje/řadí podle těchto sloupců:
- `idx_cipher_type` — zrychluje `WHERE cipher_type = ?`.
- `idx_parent_id` — zrychluje práci s `parent_id` (řazení/vazby).

Bez indexů by DB musela procházet všechny řádky (full scan). U malých dat se rozdíl nepozná, ale
je to **správný návyk**.

### `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`
- **`ENGINE=InnoDB`** — úložný engine MariaDB. InnoDB podporuje transakce a cizí klíče a je
  výchozí volbou (na rozdíl od staršího MyISAM).
- **`CHARSET=utf8mb4`** — znaková sada Unicode s plnou podporou (4 bajty/znak). **Důležité** pro
  českou diakritiku (`č`, `ř`, `ž`) i emoji. `utf8mb4` je „skutečné" UTF-8 (starší `utf8` v
  MySQL je jen 3bajtové a neumí vše).

## Životní cyklus dat
```
api.php logHistory()  ─INSERT→  tabulka history  ─SELECT→  api.php getHistory  →  crypto.js tabulka
```
Data fyzicky leží v named volume `db_data` (viz [docker-compose.md](docker-compose.md)), takže
přežijí restart kontejneru.

## Jak schéma měnit

| Chci… | Udělej… |
|-------|---------|
| Přidat sloupec | Přidej řádek do `CREATE TABLE` + uprav `INSERT`/`SELECT` v [api.php](../php/api.md) + `crypto.js` |
| Vynutit cizí klíč | `FOREIGN KEY (parent_id) REFERENCES history(id)` (InnoDB to umí) |
| Aplikovat změnu init.sql | `docker compose down -v && docker compose up` (jinak se nespustí — DB už existuje) |
| Delší klíče | `cipher_key` už je `TEXT`; pro extrémní velikost `MEDIUMTEXT`/`LONGTEXT` |

> **Pozor**: `init.sql` se spouští **jen při prvním vytvoření** databázového volume. Editace
> souboru u existující DB nic neudělá, dokud volume nesmažeš (`down -v`).

## Související
- [../php/api.md](../php/api.md) — kdo do tabulky píše a čte
- [docker-compose.md](docker-compose.md) — jak se skript spustí
- [../zaklady-technologii.md](../zaklady-technologii.md#sql-a-mariadb) — co je SQL, tabulka, index
