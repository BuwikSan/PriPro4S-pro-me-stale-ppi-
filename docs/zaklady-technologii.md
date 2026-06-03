# Základy všech technologií v projektu

Tento dokument vysvětluje **od nuly** každý jazyk a technologii použitou v projektu, vždy s
odkazem na **tvůj konkrétní kód**. Cílem je, abys po přečtení rozuměl, „co je co", a věděl, kde
se to v repu nachází.

> Python je **vynechán** (umíš ho). Zmiňuje se jen jako „černá skříňka", kterou PHP volá.

## Mapa: jak technologie spolupracují

```
        PROHLÍŽEČ (klient)                          SERVER (Docker kontejner "web")
┌───────────────────────────────┐         ┌──────────────────────────────────────────┐
│ HTML  → struktura stránky      │  HTTP   │ PHP (Apache) → api.php zpracuje požadavek │
│ CSS   → vzhled (style.css)     │ ◄─────► │   │                                      │
│ JS    → chování (crypto.js)    │  JSON   │   ├─ proc_open → Python (šifry)           │
└───────────────────────────────┘         │   └─ PDO/SQL → MariaDB (kontejner "db")   │
                                          └──────────────────────────────────────────┘
                          vše zabalené a propojené pomocí DOCKER + docker-compose
```

Obsah:
1. [HTTP a REST](#http-a-rest) — jak spolu klient a server mluví
2. [JSON](#json) — formát dat mezi nimi
3. [HTML](#html) — struktura stránek
4. [CSS](#css) — vzhled
5. [JavaScript](#javascript) — chování v prohlížeči
6. [PHP](#php) — serverový jazyk
7. [SQL a MariaDB](#sql-a-mariadb) — databáze
8. [Docker](#docker) — běhové prostředí
9. [Slovníček pojmů](#slovníček-pojmů)

---

## HTTP a REST

**HTTP** (HyperText Transfer Protocol) je protokol, kterým prohlížeč komunikuje se serverem.
Funguje jako **dotaz–odpověď**: klient pošle *request*, server vrátí *response*.

### HTTP metody (slovesa)
Každý požadavek má **metodu** — co se má udělat:
- **`GET`** — „dej mi data" (čtení, bez vedlejších efektů). V projektu: načtení historie.
- **`POST`** — „zpracuj tato data" (vytvoření/akce). V projektu: zašifrovat/dešifrovat.

V tvém kódu API rozhoduje právě podle metody:
```php
// api.php
if ($_SERVER['REQUEST_METHOD'] === 'GET'  && ...) { /* čtení historie */ }
if ($_SERVER['REQUEST_METHOD'] === 'POST') { /* crypto operace */ }
```
→ [php/api.md](php/api.md)

### Části požadavku
- **URL** + query parametry: `api.php?action=getHistory&cipher_type=hill`. PHP je čte z `$_GET`.
- **Hlavičky (headers)**: metadata, např. `Content-Type: application/json`.
- **Tělo (body)**: data (u POST). PHP je čte z `php://input`.

### Status kódy
Odpověď nese číselný kód: `200` OK, `404` nenalezeno, `500` chyba serveru. Tato aplikace status
kódy moc neřeší — úspěch/chybu signalizuje polem `success` v JSON těle.

### REST / AJAX
**REST** je styl návrhu API, kde se s daty pracuje přes HTTP metody a typicky se vyměňuje JSON.
**AJAX** = technika, kdy JavaScript volá server **na pozadí** (bez znovunačtení stránky) a
aktualizuje jen část DOMu. Přesně to dělá `crypto.js` přes `fetch()`:
```js
// crypto.js — AJAX volání, stránka se nereloaduje
const resp = await fetch('api.php', { method: 'POST', ... });
```
→ [php/stranky.md](php/stranky.md)

---

## JSON

**JSON** (JavaScript Object Notation) je textový formát pro výměnu dat. Vypadá jako JS objekt:
```json
{ "success": true, "output": "ZAŠIFROVÁNO", "records": [ { "id": 1 } ] }
```
Typy: řetězec `"text"`, číslo `42`, bool `true`/`false`, `null`, pole `[...]`, objekt `{...}`.

JSON je **lingua franca** celé aplikace — používá se na **třech** rozhraních:
1. **Prohlížeč ↔ PHP**: `fetch` posílá/přijímá JSON (`JSON.stringify` / `r.json()` v JS,
   `json_decode` / `json_encode` v PHP).
2. **PHP ↔ Python**: api.php pošle JSON na STDIN, čte JSON z STDOUT.
3. **Uvnitř DB**: klíče šifer se ukládají jako JSON řetězec do sloupce `cipher_key`.

Převody v tvém kódu:
| Jazyk | Objekt → JSON | JSON → objekt |
|-------|---------------|---------------|
| PHP | `json_encode($data)` | `json_decode($json, true)` |
| JS | `JSON.stringify(obj)` | `JSON.parse(str)` / `response.json()` |

Příklad z [api.php](php/api.md):
```php
echo json_encode(['success' => true, 'output' => $py['ciphertext']]);  // PHP pole → JSON text
$req = json_decode(file_get_contents('php://input'), true);             // JSON text → PHP pole
```

---

## HTML

**HTML** (HyperText Markup Language) popisuje **strukturu** stránky pomocí **značek (tagů)**.
Není to programovací jazyk — je to *značkovací* jazyk. Soubory: [index.php](../index.php),
[hill.php](../hill.php), [mlkem.php](../mlkem.php) (HTML s příponou `.php`).

### Element, tag, atribut
```html
<a href="hill.php">Hill Cipher</a>
│  │              │             │
│  │ atribut      │ obsah       │ uzavírací tag
│  └ otevírací tag s atributem
└ element <a> (odkaz)
```
- **Tag**: `<a>` otevírací, `</a>` uzavírací. Některé jsou „prázdné" (`<meta>`, `<br>` — bez obsahu).
- **Atribut**: `href="..."` — dodatečná informace ve formě `jméno="hodnota"`.
- **Obsah**: text/další elementy mezi tagy.

### Struktura dokumentu (z tvého kódu)
```html
<!DOCTYPE html>           <!-- typ dokumentu -->
<html lang="cs">          <!-- kořen, jazyk -->
  <head> ... </head>      <!-- metadata: titulek, kódování, odkaz na CSS -->
  <body> ... </body>      <!-- viditelný obsah -->
</html>
```

### Důležité elementy v projektu
| Element | Význam | Kde |
|---------|--------|-----|
| `<header>`, `<main>`, `<footer>`, `<nav>` | sémantické bloky stránky | všechny stránky |
| `<h1>`–`<h4>` | nadpisy (úrovně) | všude |
| `<p>` | odstavec | info-boxy |
| `<a href>` | odkaz | navigace |
| `<textarea id="input">` | víceřádkové pole | hill/mlkem |
| `<button onclick>` | tlačítko + akce | hill/mlkem |
| `<table>/<thead>/<tbody>/<tr>/<th>/<td>` | tabulka | historie |
| `<select>/<option>` | rozbalovací nabídka | filtry |
| `<div>`, `<span>` | obecné kontejnery (bez významu, pro styl) | všude |
| `<script>`, `<link>` | připojení JS / CSS | hlavička/pata |

### Klíčový atribut `id`
`id` jednoznačně pojmenuje element. JavaScript ho pak najde:
```html
<textarea id="input">       <!-- HTML -->
```
```js
document.getElementById('input').value   // JS čte obsah
```
Toto propojení (HTML `id` ↔ JS `getElementById`) je **základ** interaktivity. → [php/stranky.md](php/stranky.md)

### Události (events) inline
```html
<button onclick="encrypt()">        <!-- klik → JS funkce -->
<select onchange="applyFilter()">   <!-- změna → JS funkce -->
```
`onclick`, `onchange` napojují uživatelskou akci na JS funkci z `crypto.js`.

---

## CSS

**CSS** (Cascading Style Sheets) určuje **vzhled** HTML. Odděluje styl od struktury. Soubor:
[style.css](../style.css), připojený v hlavičce `<link rel="stylesheet" href="style.css">`.

### Pravidlo CSS
```css
selektor {
    vlastnost: hodnota;
}
```
Příklad z tvého kódu:
```css
button {
    background-color: #00ff66;   /* zelená */
    color: #0d0e15;              /* tmavý text */
    cursor: pointer;             /* ručička při najetí */
}
```

### Selektory (jak vybrat prvky) — vše v tvém style.css
| Selektor | Vybírá | Příklad |
|----------|--------|---------|
| `button` | všechny elementy daného typu | `button { ... }` |
| `.info-box` | elementy s `class="info-box"` | `.info-box { ... }` |
| `#output` | element s `id="output"` | `#output { ... }` |
| `tr.enc-row td` | `<td>` uvnitř `<tr class="enc-row">` | `tr.enc-row td { ... }` |
| `button:hover` | tlačítko při najetí myší | `button:hover { ... }` |
| `textarea:focus` | pole, když je aktivní | `textarea:focus { ... }` |
| `*` | úplně vše (reset) | `* { margin: 0; }` |

### Box model
Každý element je „krabice": **content → padding → border → margin** (zevnitř ven). V projektu:
```css
* { box-sizing: border-box; }   /* padding/border se počítají do šířky → předvídatelné rozměry */
main { padding: 30px; border: 1px solid #00ff66; }
```

### Layout — Flexbox
```css
.button-group { display: flex; gap: 10px; }   /* prvky vedle sebe s mezerou */
nav { display: flex; gap: 20px; }
```
`display: flex` udělá z kontejneru „pružný" řádek/sloupec — moderní způsob rozmístění prvků.

### Responzivita — media queries
```css
@media (max-width: 600px) {        /* pravidla jen pro úzké obrazovky (mobil) */
    .button-group { flex-direction: column; }   /* tlačítka pod sebe */
    nav { flex-direction: column; }
}
```
Spolupracuje s `<meta name="viewport">` v HTML.

### Vizuální vazba na data
CSS třídy `enc-row`, `dec-row`, `plaintext-result` (které přiřazuje `crypto.js` podle typu
záznamu) dávají tabulce barevné rozlišení šifrování/dešifrování:
```css
tr.enc-row td { border-left: 3px solid #00ff66; }   /* enc = zelený pruh */
tr.dec-row td { border-left: 3px solid #00e5ff; padding-left: 18px; }  /* dec = cyan, odsazené */
```
→ propojení s [php/stranky.md](php/stranky.md) (`renderRow`).

---

## JavaScript

**JavaScript (JS)** je programovací jazyk, který běží **v prohlížeči** a dělá stránku interaktivní.
Pozor: navzdory jménu **nemá nic společného s Javou**. Soubor: [crypto.js](../crypto.js).

### Proměnné a typy
```js
let historyData = {};      // proměnná, lze přiřadit znovu
const CIPHER_TYPE = 'hill'; // konstanta, nelze přepsat
let allRecords = [];        // pole
```
- `const` = neměnná vazba, `let` = měnitelná. (Staré `var` se dnes nepoužívá.)
- Typy: číslo, řetězec, boolean, `null`, `undefined`, objekt `{}`, pole `[]`.

### Funkce
```js
function truncate(text, len) { return text.length > len ? ... : text; }   // klasická
const fn = r => r.json();                                                  // arrow funkce
async function encrypt() { ... }                                           // asynchronní
```
- **Arrow funkce** `x => ...` je stručný zápis funkce, hojně v `.then(r => r.json())`.
- **`async`/`await`**: práce s operacemi, které trvají (síť). `await` „počká" na výsledek bez
  zmrazení stránky.

### DOM (Document Object Model)
JS vidí HTML jako **strom objektů** a může ho měnit za běhu:
```js
document.getElementById('input').value          // přečti hodnotu pole
document.getElementById('historyBody').innerHTML = '<tr>...</tr>';  // přepiš obsah tabulky
document.getElementById('status').innerHTML = `<div class="error">${msg}</div>`;
```
- `getElementById('x')` — najde element podle `id`.
- `.value` — obsah formulářového pole.
- `.innerHTML` — HTML uvnitř elementu (čtení i zápis = dynamické překreslení).

### Události
```js
document.addEventListener('DOMContentLoaded', loadHistory);  // po načtení stránky spusť loadHistory
```
Plus inline `onclick`/`onchange` v HTML (viz výše).

### Práce s polem
```js
allRecords.filter(r => ...)        // vyber prvky splňující podmínku
filtered.map(renderRow)            // přetvoř každý prvek (záznam → HTML)
.join('')                          // spoj pole řetězců do jednoho
allRecords.forEach(r => { ... })   // projdi každý prvek
```
Tyto „funkcionální" metody jsou jádro `applyFilter()`/`loadHistory()`.

### Template literals (šablonové řetězce)
```js
`api.php?action=getHistory&cipher_type=${CIPHER_TYPE}`   // zpětné apostrofy, ${...} vloží hodnotu
```
Pohodlné skládání řetězců a HTML.

### Fetch API a Promises
```js
const resp = await fetch('api.php', { method: 'POST', headers: {...}, body: JSON.stringify(obj) });
const data = await resp.json();
if (data.success) { ... }
```
- `fetch()` pošle HTTP požadavek, vrátí **Promise** (příslib budoucího výsledku).
- `await` počká na odpověď; `resp.json()` ji rozparsuje z JSON.
- Alternativní zápis přes `.then()`: `fetch(...).then(r => r.json()).then(data => ...)` — viz
  `loadHistory()`.

→ Celý JS rozebrán v [php/stranky.md](php/stranky.md).

---

## PHP

**PHP** je serverový skriptovací jazyk. Běží **na serveru** (v kontejneru `web`), generuje
odpovědi a komunikuje s databází i s Pythonem. Soubory: [api.php](../api.php), [db.php](../db.php).

### Syntaxe základy
```php
<?php                       // začátek PHP kódu
$promenna = 'hodnota';      // proměnné VŽDY začínají $
$cislo = 42;
$pole = ['a', 'b'];         // indexované pole
$mapa = ['klic' => 'hodnota'];  // asociativní pole (klíč => hodnota)
echo 'výstup';              // vypiš
```
- Každý příkaz končí **středníkem** `;`.
- Komentáře: `// řádkový`, `/* blokový */`.
- **Spojení řetězců** je tečka `.` (ne `+`!): `'a' . 'b'` → `'ab'`.

### Proměnné prostředí
```php
$db_host = getenv('DB_HOST') ?: 'db';   // přečti env proměnnou, jinak default
```
→ [php/db.md](php/db.md). Hodnoty nastavuje Docker.

### Operátory specifické pro PHP
| Operátor | Význam | Příklad v kódu |
|----------|--------|----------------|
| `.` | spojení řetězců | `'mysql:host=' . $db_host` |
| `?:` | Elvis (zkrácený ternární) | `getenv(...) ?: 'db'` |
| `??` | null coalescing | `$_GET['action'] ?? ''` |
| `=>` | klíč → hodnota / arrow fn | `['success' => true]` |
| `===` | striktní rovnost (typ i hodnota) | `$_SERVER['REQUEST_METHOD'] === 'GET'` |
| `(int)` | přetypování | `(int)$req['parent_id']` |

> `??` vs `?:`: `??` reaguje jen na `null`/neexistenci, `?:` na jakoukoli „falsy" hodnotu. V kódu
> se používají záměrně podle situace.

### Funkce a typování
```php
function logHistory(string $cipher_type, ?string $cipher_key, ?int $parent_id = null): void {
    global $pdo;
    ...
}
```
- `string`, `?string` (smí být null), `?int`, `: void` (nic nevrací) — **typové deklarace**.
- `= null` — výchozí hodnota parametru.
- `global $pdo;` — zpřístupní globální proměnnou uvnitř funkce.
- **Anonymní funkce (closure)**: `function($errno, ...) { ... }` předaná do `set_error_handler`.

### Superglobály (vestavěná pole)
| Pole | Obsah |
|------|-------|
| `$_GET` | parametry z URL (`?action=...`) |
| `$_POST` | data HTML formuláře (zde se nepoužívá — JSON jde přes `php://input`) |
| `$_SERVER` | info o požadavku (`REQUEST_METHOD`, …) |

### Ošetření chyb
```php
try {
    require_once 'db.php';
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
```
- `try/catch` zachytí výjimky. `Throwable` = nejobecnější (Exception i Error).
- `throw $e;` — výjimku „přehodí" výš.
- `$e->getMessage()` — `->` přistupuje ke členu objektu (metoda/vlastnost).

### Výstupní buffer
```php
ob_start();   // začni bufferovat výstup
ob_clean();   // zahoď nabufferovaný výstup
```
Umožňuje „přepsat" rozdělanou odpověď čistou JSON chybou. → [php/api.md](php/api.md).

### Spuštění externího procesu
```php
$proc = proc_open('python3 ...', $descriptorspec, $pipes);
fwrite($pipes[0], json_encode($data));  // STDIN
$out = stream_get_contents($pipes[1]);   // STDOUT
```
Most PHP → Python přes roury. → [php/api.md](php/api.md#část-4-callpython--most-mezi-php-a-pythonem-řádky-102118).

### Práce s DB — PDO
```php
$pdo = new PDO($dsn, $user, $pass);
$stmt = $pdo->prepare('SELECT ... WHERE cipher_type = ?');
$stmt->execute([$cipher_type]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
```
→ viz [SQL a MariaDB](#sql-a-mariadb) a [php/db.md](php/db.md).

---

## SQL a MariaDB

**Databáze** ukládá data trvale. **MariaDB** je konkrétní databázový systém (fork MySQL),
běží v kontejneru `db`. **SQL** je jazyk, kterým se s databází mluví. Schéma:
[init.sql](../init.sql).

### Tabulka, řádek, sloupec
Data jsou v **tabulkách** (jako excelové listy). Tabulka má **sloupce** (definované typy) a
**řádky** (jednotlivé záznamy). Tvoje jediná tabulka je `history` → [docker/init-sql.md](docker/init-sql.md).

### Hlavní SQL příkazy
| Příkaz | Co dělá | V projektu |
|--------|---------|-----------|
| `CREATE TABLE` | vytvoří tabulku | init.sql |
| `INSERT INTO` | vloží řádek | `logHistory()` |
| `SELECT` | čte řádky | `getHistory` |
| `UPDATE` / `DELETE` | změní / smaže | (nepoužito) |

### SELECT z tvého api.php
```sql
SELECT id, typ_operace, input, output, cipher_key, parent_id, timestamp
FROM history
WHERE cipher_type = ?
ORDER BY COALESCE(parent_id, id) DESC, id ASC
LIMIT 100
```
- `SELECT sloupce` — co vybrat.
- `FROM history` — z které tabulky.
- `WHERE podmínka` — filtr řádků.
- `ORDER BY` — řazení. `COALESCE(a, b)` vrátí první ne-NULL hodnotu (chytré řazení dec pod enc).
- `LIMIT n` — max počet řádků.

### INSERT z tvého logHistory()
```sql
INSERT INTO history (cipher_type, typ_operace, cipher_key, input, output, parent_id)
VALUES (?, ?, ?, ?, ?, ?)
```
Vyjmenuje sloupce a dodá hodnoty. `id`/`timestamp` se doplní samy.

### Datové typy (v init.sql)
`INT` (celé číslo), `VARCHAR(n)` (krátký text), `TEXT` (dlouhý text), `TIMESTAMP` (čas).
`AUTO_INCREMENT`, `PRIMARY KEY`, `NOT NULL`, `DEFAULT` — viz [docker/init-sql.md](docker/init-sql.md).

### Prepared statements (proč ten otazník `?`)
**Bezpečnostní jádro.** Hodnoty se nikdy nelepí přímo do SQL řetězce. Místo toho `?` a hodnoty
v `execute([...])`:
```php
$stmt = $pdo->prepare('... WHERE cipher_type = ?');
$stmt->execute([$cipher_type]);   // hodnota dodaná bezpečně
```
Tím se **brání SQL injection** — útočník nemůže přes vstup vložit vlastní SQL. Vždy to dělej takhle.

### Indexy
Zrychlují vyhledávání podle sloupce (jako rejstřík). V projektu `idx_cipher_type`, `idx_parent_id`.

---

## Docker

**Docker** „zabaluje" aplikaci i s prostředím (OS, knihovny, runtime) do **kontejnerů** —
izolovaných, přenositelných balíčků. Výhoda: „běží to u mě stejně jako na serveru". Soubory:
[Dockerfile](../Dockerfile), [docker-compose.yml](../docker-compose.yml).

### Image vs. kontejner
- **Image** = neměnná šablona (recept upečený do balíku). Vznikne z `Dockerfile`.
- **Kontejner** = běžící instance image. Z jednoho image lze spustit více kontejnerů.

Analogie: image = třída, kontejner = objekt.

### Dockerfile — recept na image
```dockerfile
FROM php:8.2-apache         # na čem stavíme
RUN apt-get install ...     # příkaz při buildu (vytvoří vrstvu)
RUN docker-php-ext-install pdo pdo_mysql
WORKDIR /var/www/html       # pracovní adresář
EXPOSE 80                   # port
```
- **`FROM`** — základní image.
- **`RUN`** — spustí příkaz během sestavení; každý tvoří **vrstvu (layer)**. Proto se balíčky
  instalují v jednom `RUN` (menší image).
- **`WORKDIR`**, **`EXPOSE`** — adresář, port.
→ detailně [docker/Dockerfile.md](docker/Dockerfile.md).

### docker-compose — více kontejnerů najednou
`docker-compose.yml` (jazyk **YAML**) popisuje celou aplikaci:
```yaml
services:
  web: { build: ., ports: ["80:80"], volumes: [".:/var/www/html"], environment: {...} }
  db:  { image: mariadb:11, environment: {...}, healthcheck: {...} }
volumes: { db_data: }
networks: { crypto_net: }
```
Klíčové pojmy:
- **service** — jeden kontejner (zde `web`, `db`).
- **ports `"80:80"`** — `HOST:KONTEJNER`; zpřístupní port ven (`http://localhost`).
- **volumes** — úložiště:
  - *bind mount* `.:/var/www/html` — tvůj kód živě v kontejneru (změna = hned vidět).
  - *named volume* `db_data` — trvalá data DB (přežijí restart).
- **environment** — proměnné prostředí (čte je `db.php`).
- **networks** — privátní síť; služby se vidí podle jména (`db` ↔ `web`). Proto `DB_HOST=db`.
- **depends_on + healthcheck** — `web` čeká, až je `db` „zdravá".
→ detailně [docker/docker-compose.md](docker/docker-compose.md).

### YAML stručně
- Struktura **odsazením mezerami** (tab zakázán).
- `klíč: hodnota`, seznam `- položka`, vnoření odsazením.
- Komentář `#`.

### Užitečné příkazy
```bash
docker compose up           # postav + spusť vše (popředí)
docker compose up -d        # na pozadí
docker compose down         # zastav + smaž kontejnery (data v named volume zůstanou)
docker compose down -v      # + smaž i volumes (reset DB)
docker compose build        # jen sestav image
docker compose logs -f web  # sleduj logy služby web
docker compose exec web bash # shell uvnitř kontejneru web
```

---

## Slovníček pojmů

| Pojem | Vysvětlení |
|-------|-----------|
| **Frontend** | část běžící v prohlížeči (HTML/CSS/JS) |
| **Backend** | část běžící na serveru (PHP, DB, Python) |
| **Endpoint** | adresa API, na kterou se posílají požadavky (zde `api.php`) |
| **Request / Response** | dotaz klienta / odpověď serveru (HTTP) |
| **AJAX** | volání serveru z JS na pozadí, bez reloadu stránky |
| **DOM** | stromová reprezentace HTML, kterou JS upravuje |
| **PDO** | abstrakce PHP pro práci s databází |
| **Prepared statement** | bezpečný SQL dotaz s `?` placeholdery (proti SQL injection) |
| **SQL injection** | útok vkládající škodlivé SQL přes neošetřený vstup |
| **Image / Container** | šablona / běžící instance v Dockeru |
| **Volume** | úložiště v Dockeru (bind = živý kód, named = trvalá data) |
| **Bind mount** | propojení adresáře hostitele do kontejneru |
| **Healthcheck** | test, zda je služba připravená |
| **Environment variable** | proměnná prostředí pro konfiguraci |
| **JSON** | textový formát pro výměnu dat |
| **Race condition** | chyba ze špatného časování (web startuje dřív než DB) |
| **KEM** | Key-Encapsulation Mechanism (princip ML-KEM) |
| **Closure** | anonymní funkce předaná jako hodnota |
| **Idempotentní** | bezpečně opakovatelné bez vedlejších efektů (`CREATE TABLE IF NOT EXISTS`) |

---

## Kam dál
- Backend: [php/api.md](php/api.md), [php/db.md](php/db.md)
- Frontend: [php/stranky.md](php/stranky.md)
- Infrastruktura: [docker/Dockerfile.md](docker/Dockerfile.md), [docker/docker-compose.md](docker/docker-compose.md), [docker/init-sql.md](docker/init-sql.md)
