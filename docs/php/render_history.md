# `render_history.php` — sdílené PHP renderování řádků tabulky

Zdroj: [../../render_history.php](../../render_history.php) (20 řádků)

Tento soubor obsahuje **dvě pomocné PHP funkce** pro generování HTML řádků tabulky historie.
Obě stránky ([hill.php](stranky.md), [mlkem.php](stranky.md)) ho sdílí přes `require_once`,
čímž se eliminuje duplicita renderovací logiky.

> Jsou to PHP protějšky JS funkce `renderRow()` z [crypto.js](stranky.md#cryptojs--sdílená-logika-frontendu).
> PHP verze renderuje **při prvním načtení stránky** (server-side), JS verze pak překresluje
> tabulku **po každém encrypt/decrypt** (client-side).

---

## `trunc()` — zkrácení textu

```php
function trunc(string $s, int $len): string {
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len) . '…' : $s;
}
```

- `mb_strlen` / `mb_substr` — **multibyte** varianty. Klasické `strlen`/`substr` počítají bajty,
  ne znaky. Protože projekt pracuje s českou diakritikou (UTF-8, kde `č` = 2 bajty), `mb_`
  varianty jsou nutné pro správné oříznutí na správný **počet znaků**.
- Pokud je text delší než `$len` znaků, ořízne ho a připojí `'…'` (Unicode elipsa, ne tři tečky).
- Obdoba JS funkce `truncate(text, len)` v `crypto.js` — logika identická, syntaxe jiná.

---

## `renderHistoryRow()` — jeden `<tr>` řádek tabulky

```php
function renderHistoryRow(array $r): string {
    $isEnc      = $r['typ_operace'] === 'enc';
    $rowClass   = $isEnc ? 'enc-row' : 'dec-row';
    $opLabel    = $isEnc ? '🔒 enc' : '└ 🔓 dec';
    $tdOutClass = $r['parent_id'] ? ' class="plaintext-result"' : '';
    $btn        = $isEnc
        ? '<button class="btn-decrypt" onclick="decrypt(' . (int)$r['id'] . ')">🔓 Dešifrovat</button>'
        : '';
    return '<tr class="' . $rowClass . '">'
        . '<td>' . $opLabel . '</td>'
        . '<td>' . htmlspecialchars(trunc((string)($r['input']  ?? ''), 45)) . '</td>'
        . '<td' . $tdOutClass . '>' . htmlspecialchars(trunc((string)($r['output'] ?? ''), 45)) . '</td>'
        . '<td>' . htmlspecialchars($r['timestamp']) . '</td>'
        . '<td>' . $btn . '</td>'
        . '</tr>';
}
```

### Parametr `$r`
Jedno asociativní pole — jeden řádek z databáze (výsledek `PDO::FETCH_ASSOC`). Sloupce:
`id`, `typ_operace`, `input`, `output`, `cipher_key`, `parent_id`, `timestamp`.

### Logika rozlišení enc / dec
Identická s JS `renderRow()`:

| Podmínka | enc řádek | dec řádek |
|----------|-----------|-----------|
| CSS třída `<tr>` | `enc-row` | `dec-row` |
| Label operace | `🔒 enc` | `└ 🔓 dec` |
| Tlačítko | `🔓 Dešifrovat` | — |
| Třída výstupu | — | `plaintext-result` (pokud `parent_id` není NULL) |

### `(int)$r['id']` v tlačítku
Přetypování na `int` je **bezpečnostní opatření** — brání tomu, aby se do `onclick="decrypt(...)"` 
dostala jiná hodnota než číslo. ID z databáze by mělo být vždy int, ale explicitní cast je jistota.

### `htmlspecialchars()`
Escapuje HTML speciální znaky (`<`, `>`, `&`, `"`) v hodnotách vstup/výstup/timestamp. Brání
**XSS** (Cross-Site Scripting) — bez escapování by uživatel mohl vložit `<script>` do textu
a ten by se spustil v prohlížeči. Timestamp escapujeme konzistentně i když z DB neobsahuje HTML.

> Srovnání: JS verze `renderRow()` v `crypto.js` **neescapuje** (vkládá hodnoty přímo do template
> literal). PHP verze to opravuje. Pro edukační projekt to nevadí, v produkci by JS verze měla
> escapovat také.

### `?? ''` a `(string)` cast
`$r['input'] ?? ''` — pokud je sloupec `NULL` v DB, použij prázdný řetězec. `(string)` cast
brání `TypeError` při předávání potenciálního `null` do `trunc()`, která očekává `string`.

---

## Jak se soubor používá

### V `hill.php` / `mlkem.php`
```php
require_once 'render_history.php';
// ...
foreach ($records as $r): echo renderHistoryRow($r); endforeach;
```

`require_once` = načti soubor jednou (opakované volání ho přeskočí). Funkce `trunc` a
`renderHistoryRow` jsou pak dostupné v celém souboru.

### Tok při načtení stránky

```
HTTP GET hill.php
  │
  ├─ PHP: SELECT záznamy z DB
  ├─ PHP: foreach → renderHistoryRow() → HTML řádky → vloženy do <tbody>
  ├─ PHP: json_encode($records) → vloženo jako INITIAL_RECORDS do <script>
  │
  └─ Prohlížeč: stránka se zobrazí s tabulkou OKAMŽITĚ (žádný extra fetch)
       └─ JS DOMContentLoaded: naplní allRecords z INITIAL_RECORDS
            → filtry fungují bez dalšího volání serveru
```

---

## Přidání nového sloupce do tabulky

1. Přidej sloupec do `init.sql` + `SELECT` v [api.php](api.md) i v stránkách.
2. Přidej `<td>` do `renderHistoryRow()` zde.
3. Přidej `<th>` do `<thead>` v `hill.php` a `mlkem.php`.
4. Přidej `<td>` do JS `renderRow()` v `crypto.js` (pro refresh po encrypt/decrypt).

## Související

- [stranky.md](stranky.md) — kde se funkce volá + jak JS `renderRow` pokrývá refresh
- [api.md](api.md) — zdroj dat (SELECT z DB)
- [../docker/init-sql.md](../docker/init-sql.md) — schéma sloupců pole `$r`
