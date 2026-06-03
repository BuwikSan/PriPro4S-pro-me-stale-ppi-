# `api.php` — REST API router (srdce backendu)

Zdroj: [../../api.php](../../api.php) (129 řádků)

Toto je **nejdůležitější PHP soubor** v projektu. Je to jediný backend endpoint. Dělá tři věci:

1. Přijímá HTTP požadavky od `crypto.js` (frontend) a vrací **JSON** odpovědi.
2. Volá Python (`cipher_wrapper.py`) pro vlastní šifrování/dešifrování.
3. Ukládá a čte historii operací z databáze (přes `$pdo` z [db.php](db.md)).

Žádný HTML. Vstup i výstup je čisté JSON — typický **REST/AJAX backend**.

---

## Část 1: Inicializace a zachytávání chyb (řádky 1–17)

```php
<?php
ob_start();
header('Content-Type: application/json');
```

### `ob_start()` — output buffering
Zapne **výstupní buffer**. Cokoli se od teď „vytiskne" (`echo`), se neodešle hned klientovi,
ale uloží se do paměti. To dovoluje buffer kdykoli **smazat** (`ob_clean()`) a poslat místo něj
něco jiného. K čemu? Když nastane chyba uprostřed generování odpovědi, můžeme zahodit půlhotový
výstup a poslat čistou JSON chybu místo rozbité odpovědi.

### `header('Content-Type: application/json')`
Nastaví HTTP hlavičku odpovědi — říká prohlížeči: „to, co posílám, je JSON". Hlavičky se musí
poslat **před** jakýmkoli tělem odpovědi. Díky `ob_start()` je tu jistota, že žádné tělo zatím
neuniklo.

```php
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => "PHP Error [$errno]: $errstr in $errfile:$errline"]);
    exit(1);
});
```

### Vlastní error handler
`set_error_handler()` zaregistruje **vlastní funkci**, která se zavolá při PHP chybě (warning,
notice apod.). Místo aby PHP vypsalo HTML chybu doprostřed JSON odpovědi (a rozbilo ji), tento
handler:
1. `ob_clean()` — vymaže dosavadní buffer,
2. `echo json_encode([...])` — pošle chybu jako **validní JSON**,
3. `exit(1)` — ukončí skript (kód 1 = chyba).

Předané argumenty popisují chybu: číslo (`$errno`), text (`$errstr`), soubor (`$errfile`),
řádek (`$errline`). Argument funkce je **anonymní funkce (closure)** — funkce bez jména předaná
jako hodnota.

```php
try {
    require_once 'db.php';
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit(1);
}
```

### Načtení DB s ošetřením
`require_once 'db.php'` spustí [db.php](db.md), čímž vznikne `$pdo`. Pokud se připojení nezdaří
(po 10 pokusech vyhodí výjimku), `catch (Throwable $e)` ji zachytí a pošle JSON chybu.

> `Throwable` je **nejobecnější** typ chyby v PHP — zachytí jak `Exception`, tak `Error`. Jistota,
> že nic neproklouzne.

---

## Část 2: GET — čtení historie (řádky 19–32)

```php
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'getHistory') {
    $cipher_type = $_GET['cipher_type'] ?? 'hill';
    $stmt = $pdo->prepare(
        'SELECT id, typ_operace, input, output, cipher_key, parent_id, timestamp
         FROM history
         WHERE cipher_type = ?
         ORDER BY COALESCE(parent_id, id) DESC, id ASC
         LIMIT 100'
    );
    $stmt->execute([$cipher_type]);
    echo json_encode(['records' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}
```

### Podmínka vstupu
- `$_SERVER['REQUEST_METHOD']` — HTTP metoda požadavku (`GET`, `POST`, …).
- `$_GET['action']` — parametr z URL (`?action=getHistory`). Frontend volá
  `api.php?action=getHistory&cipher_type=hill` (viz `loadHistory()` v [stranky.md](stranky.md)).
- `?? ''` je **null coalescing operátor**: `$_GET['action'] ?? ''` znamená „použij `$_GET['action']`,
  a pokud neexistuje (není nastaveno), použij `''`". Brání chybě „undefined index".

Tato větev se spustí jen pro `GET` požadavek s `action=getHistory`.

### Prepared statement (připravený dotaz)
```php
$stmt = $pdo->prepare('SELECT ... WHERE cipher_type = ? ...');
$stmt->execute([$cipher_type]);
```
**Klíčový bezpečnostní vzor.** Místo vlepení hodnoty přímo do SQL řetězce se použije
**zástupný znak** `?` a hodnota se předá zvlášť v `execute([...])`. Tím se **zabrání SQL injection** —
uživatelská data nemůžou „uniknout" a stát se součástí SQL příkazu.

### SQL dotaz
- `SELECT id, typ_operace, ...` — vybere sloupce (struktura tabulky: [init-sql.md](../docker/init-sql.md)).
- `WHERE cipher_type = ?` — jen záznamy dané šifry (`hill` nebo `mlkem`).
- `ORDER BY COALESCE(parent_id, id) DESC, id ASC` — **chytré řazení**:
  - `COALESCE(parent_id, id)` vrátí `parent_id`, a když je `NULL` (záznam je sám rodič = enc),
    vrátí vlastní `id`. Tím se každý dec záznam „přilepí" ke svému enc rodiči se stejnou
    řadicí hodnotou.
  - `DESC` na této hodnotě → nejnovější skupiny nahoře.
  - `, id ASC` → uvnitř skupiny je enc (nižší id) před svým dec (vyšší id).
  - **Výsledek**: každý dešifrovací záznam se zobrazí hned pod svým šifrovacím rodičem.
- `LIMIT 100` — max 100 řádků (ochrana proti zahlcení).

### Vrácení dat
- `$stmt->fetchAll(PDO::FETCH_ASSOC)` — načte **všechny** řádky jako pole asociativních polí
  (klíč = jméno sloupce). `FETCH_ASSOC` = jen jména sloupců, ne číselné indexy.
- `json_encode(['records' => ...])` — zabalí do JSON `{ "records": [ ... ] }`.
- `exit;` — konec, dál se nepokračuje.

---

## Část 3: POST — kryptografické operace (řádky 34–100)

```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $req       = json_decode(file_get_contents('php://input'), true);
    $op        = $req['operation']  ?? '';
    $text      = $req['input']      ?? '';
    $ckey      = $req['cipher_key'] ?? '';
    $parent_id = isset($req['parent_id']) ? (int)$req['parent_id'] : null;
```

### Načtení JSON těla
- `file_get_contents('php://input')` — přečte **surové tělo** HTTP požadavku. `php://input` je
  speciální „soubor" reprezentující raw POST data. Používá se, protože frontend posílá JSON
  (ne klasický HTML formulář — pro ten by bylo `$_POST`).
- `json_decode($json, true)` — převede JSON řetězec na PHP pole. Druhý argument `true` znamená
  **asociativní pole** místo objektu.
- Pak se z pole vytahají parametry s `?? ''` defaulty.
- `$parent_id`: pokud klient poslal `parent_id`, přetypuje se na `int` (`(int)`), jinak `null`.
  Slouží k propojení dec záznamu s jeho enc rodičem.

```php
    if (!$op || !$text) {
        echo json_encode(['success' => false, 'error' => 'Chybějící parametry']);
        exit;
    }
```
Validace: bez operace nebo bez textu nemá smysl pokračovat. `!$op` je pravdivé, když je `$op`
prázdné/`''`/`null`.

### `switch ($op)` — router operací
```php
    try { switch ($op) {
        case 'hill_enc':  ...
        case 'hill_dec':  ...
        case 'mlkem_enc': ...
        case 'mlkem_dec': ...
        default: ...
    } } catch (Throwable $e) { ... }
```
Rozcestník podle hodnoty `operation`. Frontend posílá `CIPHER_TYPE + '_enc'` nebo `+ '_dec'`,
tedy jednu ze čtyř hodnot. Celý `switch` je obalen `try/catch` — jakákoli výjimka skončí jako
JSON chyba `'Server: ...'`.

#### `case 'hill_enc'` (šifrování Hill)
```php
$py = callPython(['operation' => 'hill_enc', 'text' => $text]);
if ($py['success']) {
    logHistory('hill', 'enc', json_encode($py['keys_data']), $text, $py['ciphertext']);
    echo json_encode(['success' => true, 'output' => $py['ciphertext']]);
} else { echo json_encode($py); }
```
1. Zavolá Python s operací `hill_enc` a textem.
2. Python vrátí ciphertext + **klíče** (`keys_data` — matice + inverzní matice + padding).
3. `logHistory()` uloží do DB: typ `hill`, operace `enc`, klíče (jako JSON v `cipher_key`),
   vstup a výstup.
4. Vrátí klientovi `{ success: true, output: <ciphertext> }`.
5. Při selhání Pythonu pošle jeho chybovou odpověď dál.

> **Důležité**: klíče Hill šifry se generují náhodně při každém šifrování a **uloží se do DB**.
> Bez nich nelze dešifrovat — proto je dešifrování dostupné jen z historie.

#### `case 'hill_dec'` (dešifrování Hill)
```php
$keys_data = json_decode($ckey, true);
if (!$keys_data || empty($keys_data['keys'])) {
    echo json_encode(['success' => false, 'error' => 'Chybí klíč pro dešifrování.']);
    break;
}
$py = callPython(['operation' => 'hill_dec', 'text' => $text, 'keys_data' => $keys_data]);
if ($py['success']) {
    logHistory('hill', 'dec', null, $text, $py['plaintext'], $parent_id);
    echo json_encode(['success' => true, 'output' => $py['plaintext']]);
} else { echo json_encode($py); }
```
1. `$ckey` (z `cipher_key` daného enc záznamu) se dekóduje z JSON zpět na pole klíčů.
2. Validace, že klíče existují.
3. Python dešifruje a vrátí `plaintext`.
4. `logHistory()` uloží dec záznam — `cipher_key` je `null` (dec klíč neukládá),
   a předá `$parent_id` (odkaz na enc rodiče).

#### `case 'mlkem_enc'` (šifrování ML-KEM)
```php
$py = callPython(['operation' => 'mlkem_enc', 'text' => $text]);
if ($py['success']) {
    $ckey_json = json_encode(['pk' => $py['pk'], 'c_kem' => $py['c_kem']]);
    logHistory('mlkem', 'enc', $ckey_json, $text, $py['ct']);
    echo json_encode(['success' => true, 'output' => $py['ct']]);
} else { echo json_encode($py); }
```
ML-KEM je KEM (key-encapsulation mechanism). Python vrací tři Base64 hodnoty:
- `ct` — zašifrovaný text (ciphertext),
- `pk` — privátní/decaps klíč,
- `c_kem` — zapouzdřený sdílený klíč.

Do `cipher_key` se uloží `pk` + `c_kem` (to, co je nutné k dešifrování).

#### `case 'mlkem_dec'` (dešifrování ML-KEM)
```php
$kd = json_decode($ckey, true);
if (!$kd || empty($kd['pk']) || empty($kd['c_kem'])) { /* chyba */ }
$py = callPython(['operation' => 'mlkem_dec', 'ct' => $text, 'pk' => $kd['pk'], 'c_kem' => $kd['c_kem']]);
...
```
Z uloženého `cipher_key` vytáhne `pk` a `c_kem`, pošle je s ciphertextem Pythonu, dostane plaintext,
uloží dec záznam s `parent_id`.

#### `default`
Neznámá operace → JSON chyba `'Neznámá operace'`.

---

## Část 4: `runEnc()` a `runDec()` — sdílená logika operací

Původní switch měl každý `case` takřka totožný průběh: zavolej Python → zkontroluj úspěch → uloži
do DB → vrať JSON. Rozdíl byl jen v parametrech. Refaktoring extrahoval tuto logiku do dvou
pomocných funkcí, `switch` teď obsahuje **jen to, co je opravdu unikátní** pro každou šifru.

```php
function runEnc(string $cipher_type, array $py_payload, string $text, callable $extract): void {
    $py = callPython($py_payload);
    if (!$py['success']) { echo json_encode($py); return; }
    [$output, $cipher_key] = $extract($py);
    logHistory($cipher_type, 'enc', $cipher_key, $text, $output);
    echo json_encode(['success' => true, 'output' => $output]);
}
```
- `callable $extract` — **arrow funkce** předaná volajícím. Říká: „jak z odpovědi Pythonu vytáhnout
  `[output, cipher_key]`". Pro Hill je to `fn($py) => [$py['ciphertext'], json_encode($py['keys_data'])]`,
  pro ML-KEM `fn($py) => [$py['ct'], json_encode(['pk' => ..., 'c_kem' => ...])]`.
- `[$output, $cipher_key] = $extract($py)` — **destructuring assignment**: PHP 7.1+, přiřadí
  první prvek pole do `$output`, druhý do `$cipher_key`.

```php
function runDec(string $cipher_type, array $py_payload, string $text, ?int $parent_id): void {
    $py = callPython($py_payload);
    if (!$py['success']) { echo json_encode($py); return; }
    logHistory($cipher_type, 'dec', null, $text, $py['plaintext'], $parent_id);
    echo json_encode(['success' => true, 'output' => $py['plaintext']]);
}
```
Dec je jednodušší — oba algoritmy vrací `$py['plaintext']`, takže žádný `$extract` callback
není potřeba.

### Výsledný switch
```php
case 'hill_enc':
    runEnc('hill', ['operation' => 'hill_enc', 'text' => $text], $text,
        fn($py) => [$py['ciphertext'], json_encode($py['keys_data'])]
    );
    break;

case 'hill_dec':
    $keys_data = json_decode($ckey, true);
    if (!$keys_data || empty($keys_data['keys'])) { /* chyba */ break; }
    runDec('hill', ['operation' => 'hill_dec', 'text' => $text, 'keys_data' => $keys_data], $text, $parent_id);
    break;
// ... analogicky mlkem_enc / mlkem_dec
```
Validace klíče zůstává v `case` — liší se pole, která se kontrolují (`keys` vs `pk`/`c_kem`).

---

## Část 5: `callPython()` — most mezi PHP a Pythonem

```php
function callPython(array $data): array {
    $pipes = [];
    $proc = proc_open('python3 /var/www/html/python/cipher_wrapper.py', [
        0 => ['pipe', 'r'],   // STDIN  procesu  (PHP do něj píše)
        1 => ['pipe', 'w'],   // STDOUT procesu  (PHP z něj čte)
        2 => ['pipe', 'w']    // STDERR procesu  (chyby)
    ], $pipes);
    if (!is_resource($proc)) return ['success' => false, 'error' => 'Nelze spustit Python'];
    fwrite($pipes[0], json_encode($data));   // pošli JSON na STDIN
    fclose($pipes[0]);                        // zavři STDIN → Python ví, že vstup skončil
    $out = stream_get_contents($pipes[1]);    // přečti celý STDOUT
    $err = stream_get_contents($pipes[2]);    // přečti celý STDERR
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($proc);                        // počkej na konec procesu
    if ($err) return ['success' => false, 'error' => 'Python: ' . trim($err)];
    return json_decode($out, true) ?: ['success' => false, 'error' => 'Špatná odpověď'];
}
```

### Jak to funguje — `proc_open()`
PHP zde **spustí samostatný proces** (`python3 cipher_wrapper.py`) a komunikuje s ním přes
**roury (pipes)**. Je to klasický unixový vzor „pošli data přes STDIN, čti výsledek z STDOUT".

Druhý argument je **deskriptorová specifikace** — mapuje tři standardní proudy procesu:
| Index | Proud | `['pipe', ...]` | Význam |
|-------|-------|-----------------|--------|
| 0 | STDIN | `'r'` (proces čte) | PHP sem **píše** vstup |
| 1 | STDOUT | `'w'` (proces píše) | PHP odsud **čte** výstup |
| 2 | STDERR | `'w'` (proces píše) | PHP odsud čte chyby |

> `'r'`/`'w'` je z pohledu **procesu**: STDIN proces *čte* (`r`), STDOUT *zapisuje* (`w`).

### Postup
1. **Zapiš** JSON na STDIN procesu (`fwrite`), pak STDIN **zavři** (`fclose($pipes[0])`).
   Zavření je nutné — signalizuje Pythonu „konec vstupu", jinak by `json.load(sys.stdin)` čekal donekonečna.
2. **Přečti** celý STDOUT a STDERR (`stream_get_contents`).
3. `proc_close()` — počká na ukončení procesu a uklidí.
4. Pokud něco přišlo na STDERR → vrať to jako chybu.
5. Jinak dekóduj STDOUT z JSON. `?: [...]` ošetří případ, kdy výstup není validní JSON.

### Protějšek na straně Pythonu
`cipher_wrapper.py` čte `json.load(sys.stdin)`, podle `operation` zavolá příslušnou funkci a
vytiskne `print(json.dumps(resp))`. (Detaily Pythonu zde nerozebíráme — umíš ho.)

> **Návrhová poznámka**: spouštět nový Python proces na *každý* požadavek je pomalé a u zátěže
> neefektivní. Pro edukační aplikaci to stačí. Alternativy: dlouhoběžící Python služba (FastAPI),
> nebo PHP rozšíření. Cesta `/var/www/html/python/...` je absolutní, protože pracovní adresář procesu
> nemusí být zaručen. Skript je v `python/` podsložce od přechodu na organizovanou strukturu.

---

## Část 5: `logHistory()` — zápis do DB (řádky 120–126)

```php
function logHistory(string $cipher_type, string $typ_operace, ?string $cipher_key,
                    string $input, string $output, ?int $parent_id = null): void {
    global $pdo;
    $stmt = $pdo->prepare(
        'INSERT INTO history (cipher_type, typ_operace, cipher_key, input, output, parent_id)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$cipher_type, $typ_operace, $cipher_key, $input, $output, $parent_id]);
}
```

- **Typované parametry**: `string`, `?string` (nullable — smí být `null`), `?int`. `: void`
  = funkce nic nevrací.
- `global $pdo;` — `$pdo` vzniklo v `db.php` v globálním rozsahu. Uvnitř funkce není automaticky
  vidět, proto `global` zpřístupní globální proměnnou. (Čistší by bylo předávat `$pdo` jako
  argument, ale `global` je tu jednoduchá volba.)
- Opět **prepared statement** se šesti `?` — bezpečné vkládání. Pořadí hodnot v `execute([...])`
  musí odpovídat pořadí `?`.
- `id` a `timestamp` se nevkládají — doplní je DB sama (`AUTO_INCREMENT` a `DEFAULT CURRENT_TIMESTAMP`,
  viz [init-sql.md](../docker/init-sql.md)).

---

## Část 6: Fallback (řádek 128)

```php
echo json_encode(['success' => false, 'error' => 'Neplatný požadavek']);
```
Sem se kód dostane, jen když požadavek **nebyl** ani vyhovující GET, ani POST (každá z těch
větví končí `exit`). Pošle obecnou chybu.

---

## Kontrakt odpovědí (důležité pro úpravy frontendu)

| Situace | JSON odpověď |
|---------|--------------|
| Úspěšná operace | `{ "success": true, "output": "<výsledek>" }` |
| Chyba | `{ "success": false, "error": "<popis>" }` |
| Historie (GET) | `{ "records": [ {…}, {…} ] }` |

Frontend (`crypto.js`) na to spoléhá: kontroluje `data.success` a čte `data.output` / `data.error`
/ `data.records`. Když změníš tvar odpovědi, **musíš změnit i `crypto.js`**.

## Jak modul měnit

| Chci… | Udělej… |
|-------|---------|
| Přidat novou šifru | Přidej `case 'xxx_enc'` a `case 'xxx_dec'` do `switch`; přidej obsluhu i v Pythonu |
| Změnit limit historie | Uprav `LIMIT 100` v GET dotazu |
| Přidat sloupec do historie | Uprav `init.sql`, `SELECT`, `INSERT` v `logHistory` i `crypto.js` |
| Logovat i neúspěchy | Přidej `logHistory(...)` do `else` větví |
| Jiné řazení historie | Uprav `ORDER BY` |

## Související
- [db.md](db.md) — odkud se bere `$pdo`
- [stranky.md](stranky.md) + `crypto.js` — kdo API volá
- [../docker/init-sql.md](../docker/init-sql.md) — schéma `history`
- [../zaklady-technologii.md](../zaklady-technologii.md) — PHP, JSON, HTTP, SQL základy
