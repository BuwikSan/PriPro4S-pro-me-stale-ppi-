# HTML stránky — `index.php`, `hill.php`, `mlkem.php` (+ `crypto.js`)

Zdroje:
- [../../index.php](../../index.php) — domovská stránka
- [../../hill.php](../../hill.php) — stránka Hill Cipher
- [../../mlkem.php](../../mlkem.php) — stránka ML-KEM
- [../../crypto.js](../../crypto.js) — sdílená JS logika obou šifrovacích stránek
- [../../style.css](../../style.css) — vzhled (zmíněno níže)

> **Pozor na název**: soubory mají příponu `.php`, ale je v nich **čisté HTML** — ani jeden
> neobsahuje `<?php ... ?>`. Proč tedy `.php`? Aby je Apache poslal přes PHP interpret (pro
> případ budoucí dynamiky) a aby odkazy jako `href="hill.php"` seděly. Funkčně se chovají jako
> statické HTML stránky; veškerá „logika" je v `crypto.js`, který volá [api.php](api.md).

---

## `index.php` — domovská (rozcestník)

Zdroj: [../../index.php](../../index.php)

Statická úvodní stránka. Nemá žádný JS ani formulář — jen představuje projekt a nabízí dva
odkazy na šifrovací stránky.

### Kostra HTML dokumentu (společná všem stránkám)
```html
<!DOCTYPE html>            <!-- režim standardů moderního prohlížeče -->
<html lang="cs">          <!-- jazyk = čeština (pro čtečky, SEO) -->
<head>
    <meta charset="UTF-8">                                  <!-- kódování → česká diakritika -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  <!-- responzivita -->
    <title>Kryptografické Šifry</title>                     <!-- titulek v záložce -->
    <link rel="stylesheet" href="style.css">                <!-- připojení CSS -->
</head>
<body> ... </body>
</html>
```
- `<!DOCTYPE html>` — povinná první řádka, přepne prohlížeč do „standards mode".
- `<meta charset="UTF-8">` — bez něj by se česká písmena (`č`, `ř`, `ž`) zobrazila rozbitě.
- `<meta name="viewport" ...>` — nutné, aby stránka byla použitelná na mobilu (spolupracuje
  s `@media` v CSS).
- `<link rel="stylesheet" href="style.css">` — načte vzhled.

### Obsah
- `<header>` s názvem a podtitulem.
- `<div class="container"><main>` — hlavní obsahový blok (CSS mu dává rámeček a šířku).
- `.info-box` — stylované „karty" s textem o projektu a návodem.
- `<nav>` se dvěma `<a>` odkazy na `hill.php` a `mlkem.php` (inline styly přímo v `style=`).
- `<footer>` — patička.

> Tato stránka demonstruje **čistou strukturu** bez interaktivity — dobrý referenční bod, než
> přejdeš na složitější `hill.php`/`mlkem.php`.

---

## `hill.php` a `mlkem.php` — šifrovací stránky

Zdroje: [../../hill.php](../../hill.php), [../../mlkem.php](../../mlkem.php)

Obě stránky jsou **téměř identické** — liší se jen textem (názvy, vysvětlení matematiky) a
**jednou klíčovou proměnnou**. Mají stejnou strukturu:

1. vstupní pole + tlačítko Šifrovat,
2. tabulku historie s filtry,
3. `.info-box` s vysvětlením dané šifry,
4. připojení `crypto.js`.

### Vstupní část
```html
<div class="form-group">
    <label for="input">Vstupní text:</label>
    <textarea id="input" placeholder="Zadej text ke zašifrování..."></textarea>
</div>
<div class="button-group">
    <button onclick="encrypt()">🔒 Šifrovat</button>
</div>
<div id="status"></div>
```
- `<textarea id="input">` — pole pro text. `id="input"` je důležité: `crypto.js` ho čte přes
  `document.getElementById('input')`.
- `<button onclick="encrypt()">` — kliknutí zavolá funkci `encrypt()` z `crypto.js`. `onclick`
  je inline navázání události.
- `<div id="status">` — sem `crypto.js` vypisuje stavové hlášky (⏳/✓/❌).
- `<label for="input">` — `for` propojuje popisek s polem (kliknutí na popisek zaměří pole).

### Tabulka historie
```html
<div class="filter-bar">
    <select id="filterType" onchange="applyFilter()"> ... </select>
    <select id="filterTime" onchange="applyFilter()"> ... </select>
</div>
<table>
    <thead><tr><th>Operace</th><th>Vstup</th>...</tr></thead>
    <tbody id="historyBody">
        <tr><td colspan="5" class="empty-row">Zatím žádné záznamy.</td></tr>
    </tbody>
</table>
```
- Dva `<select>` filtry: typ operace (vše/enc/dec) a čas (vše/24h/7 dní). `onchange="applyFilter()"`
  → při změně se zavolá `applyFilter()` v `crypto.js`.
- `<tbody id="historyBody">` — **dynamicky plněné tělo** tabulky. Výchozí řádek „Zatím žádné
  záznamy" `crypto.js` přepíše skutečnými daty.
- `<thead>` = neměnná hlavička, `<tbody>` = měnitelná data.

### Klíčový rozdíl mezi stránkami
Úplně dole:
```html
<!-- hill.php -->
<script>const CIPHER_TYPE = 'hill';</script>
<script src="crypto.js"></script>
```
```html
<!-- mlkem.php -->
<script>const CIPHER_TYPE = 'mlkem';</script>
<script src="crypto.js"></script>
```
**Toto je celé tajemství sdílení kódu.** Každá stránka nastaví globální konstantu `CIPHER_TYPE`
(`'hill'` nebo `'mlkem'`) **před** načtením `crypto.js`. Tentýž JS soubor pak podle této hodnoty
volá správnou operaci (`hill_enc` / `mlkem_enc`). Jeden JS, dvě stránky — žádná duplicita.

> **Pořadí `<script>` je důležité**: nejprve se definuje `CIPHER_TYPE`, pak se načte `crypto.js`,
> který ji používá. Kdybys to prohodil, `crypto.js` by `CIPHER_TYPE` neviděl.

---

## `crypto.js` — sdílená logika frontendu

Zdroj: [../../crypto.js](../../crypto.js)

Toto je „mozek" obou šifrovacích stránek. Komunikuje s [api.php](api.md) přes `fetch()` a
vykresluje historii. Není to PHP, ale je nutné ho znát, protože spojuje stránky s backendem.

### Globální stav
```js
let historyData = {};     // mapa id → záznam (rychlé dohledání při dešifrování)
let allRecords = [];      // plný seznam záznamů (pro client-side filtrování)
```

### `loadHistory()` — načtení historie z API
```js
function loadHistory() {
    fetch(`api.php?action=getHistory&cipher_type=${CIPHER_TYPE}`)
        .then(r => r.json())
        .then(data => {
            historyData = {};
            allRecords = data.records || [];
            allRecords.forEach(r => { historyData[r.id] = r; });
            applyFilter();
        });
}
```
- `fetch(...)` — pošle **GET** požadavek na API. Šablona `` `...${CIPHER_TYPE}` `` (template
  literal) vloží `'hill'`/`'mlkem'`.
- `.then(r => r.json())` — odpověď (Promise) se převede z JSON.
- Uloží `data.records` do `allRecords` a vytvoří mapu `historyData[id]`.
- `applyFilter()` překreslí tabulku.

> Tady se uzavírá kruh: tento `cipher_type` parametr čte `api.php` v GET větvi (viz
> [api.md](api.md#část-2-get--čtení-historie-řádky-1932)).

### `applyFilter()` — filtrování a vykreslení
```js
function applyFilter() {
    const typeFilter = document.getElementById('filterType').value;
    const timeFilter = document.getElementById('filterTime').value;
    const now = new Date();
    const filtered = allRecords.filter(r => {
        if (typeFilter !== 'all' && r.typ_operace !== typeFilter) return false;
        if (timeFilter !== 'all') {
            const hoursAgo = (now - new Date(r.timestamp.replace(' ', 'T'))) / 3600000;
            if (timeFilter === 'today' && hoursAgo > 24) return false;
            if (timeFilter === 'week' && hoursAgo > 168) return false;
        }
        return true;
    });
    const tbody = document.getElementById('historyBody');
    tbody.innerHTML = filtered.length
        ? filtered.map(renderRow).join('')
        : '<tr><td colspan="5" class="empty-row">Žádné záznamy.</td></tr>';
}
```
- Filtruje se **na klientu** (data už jsou stažená — rychlé, bez dalšího volání serveru).
- `r.timestamp.replace(' ', 'T')` — převede `"2025-01-01 12:00:00"` na ISO `"2025-01-01T12:00:00"`,
  aby `new Date()` fungoval spolehlivě napříč prohlížeči.
- `(now - date) / 3600000` — rozdíl v milisekundách děleno 3 600 000 = počet hodin (24h, 168h=7 dní).
- `.map(renderRow).join('')` — každý záznam se převede na HTML řádek a spojí do jednoho řetězce.
- `tbody.innerHTML = ...` — nahradí obsah tabulky.

### `renderRow(r)` — jeden řádek tabulky
```js
function renderRow(r) {
    const isEnc = r.typ_operace === 'enc';
    const rowClass = isEnc ? 'enc-row' : 'dec-row';
    const opLabel = isEnc ? '🔒 enc' : '└ 🔓 dec';
    const btn = isEnc
        ? `<button class="btn-decrypt" onclick="decrypt(${r.id})">🔓 Dešifrovat</button>`
        : '';
    return `<tr class="${rowClass}"> ... </tr>`;
}
```
- Enc řádky dostanou třídu `enc-row` (zelený akcent) a **tlačítko Dešifrovat**.
- Dec řádky dostanou `dec-row` (odsazené, cyan — vizuálně „dítě" enc řádku) a žádné tlačítko.
- `onclick="decrypt(${r.id})"` — tlačítko předá `id` záznamu funkci `decrypt`.
- Vizuál tříd `enc-row`/`dec-row`/`plaintext-result` je v [../../style.css](../../style.css).

### `encrypt()` — odeslání textu k zašifrování
```js
async function encrypt() {
    const text = document.getElementById('input').value.trim();
    if (!text) { setStatus('❌ Zadej text', 'error'); return; }
    setStatus('⏳ Šifrování...', 'success');
    const resp = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ operation: CIPHER_TYPE + '_enc', input: text })
    });
    const data = await resp.json();
    if (data.success) {
        document.getElementById('input').value = '';
        setStatus('✓ Zašifrováno – viz tabulka', 'success');
        loadHistory();
    } else { setStatus('❌ ' + data.error, 'error'); }
}
```
- `async`/`await` — moderní zápis asynchronního kódu (čeká na odpověď bez blokování UI).
- Pošle **POST** s JSON tělem `{ operation: 'hill_enc', input: text }` na `api.php`.
- Při úspěchu vyčistí pole a znovu načte historii (kde se objeví nový enc záznam).
- `setStatus()` vypisuje hlášky do `<div id="status">`.

### `decrypt(id)` — dešifrování z historie
```js
async function decrypt(id) {
    const r = historyData[id];
    const resp = await fetch('api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            operation: CIPHER_TYPE + '_dec',
            input: r.output,        // ciphertext z enc záznamu
            cipher_key: r.cipher_key,  // klíč uložený u enc záznamu
            parent_id: r.id         // odkaz na rodiče → propojení v tabulce
        })
    });
    const data = await resp.json();
    if (data.success) { loadHistory(); } else { setStatus('❌ ' + data.error, 'error'); }
}
```
- Z mapy `historyData` vezme enc záznam a pošle jeho `output` (ciphertext) + `cipher_key` (klíč)
  zpět na API jako dec operaci.
- `parent_id: r.id` — díky tomu se nový dec záznam v tabulce zobrazí **pod** svým enc rodičem
  (řazení řeší SQL v [api.php](api.md), styl odsazení CSS).

### Pomocné funkce
```js
function truncate(text, len) { ... }   // zkrátí dlouhý text na len znaků + …
function setStatus(msg, type) { ... }   // vypíše hlášku, po 4 s ji smaže
document.addEventListener('DOMContentLoaded', loadHistory);  // načti historii po načtení stránky
```
- `DOMContentLoaded` — událost „HTML je načtené". Po ní se automaticky zavolá `loadHistory()`,
  takže tabulka se naplní hned po otevření stránky.

---

## Kompletní tok jedné operace (od kliknutí po zobrazení)

```
1. Uživatel napíše text, klikne 🔒 Šifrovat
2. crypto.js: encrypt() → fetch POST api.php { operation:'hill_enc', input:text }
3. api.php: switch → case 'hill_enc' → callPython() → spustí cipher_wrapper.py
4. Python: zašifruje, vrátí JSON s ciphertextem + klíči na STDOUT
5. api.php: logHistory() uloží do MariaDB, vrátí { success:true, output:ciphertext }
6. crypto.js: data.success → loadHistory()
7. crypto.js: fetch GET api.php?action=getHistory → nové záznamy
8. api.php: SELECT z DB → JSON records
9. crypto.js: applyFilter() → renderRow() → tabulka se překreslí, řádek je vidět
```

## Jak stránky měnit

| Chci… | Udělej… |
|-------|---------|
| Změnit texty/vysvětlení | Edituj přímo HTML v `hill.php` / `mlkem.php` / `index.php` |
| Přidat třetí šifru | Zkopíruj `hill.php` → `xxx.php`, změň `CIPHER_TYPE = 'xxx'`, přidej `case` v `api.php` + Python |
| Změnit vzhled | Edituj [../../style.css](../../style.css) (třídy `enc-row`, `info-box`, …) |
| Přidat filtr | Přidej `<select>` do HTML + větev do `applyFilter()` v `crypto.js` |
| Změnit chování tlačítek | Edituj `encrypt()` / `decrypt()` v `crypto.js` |

## Související
- [api.md](api.md) — backend, který tyto stránky volají
- [../zaklady-technologii.md](../zaklady-technologii.md) — HTML, CSS, JavaScript, fetch/AJAX základy
