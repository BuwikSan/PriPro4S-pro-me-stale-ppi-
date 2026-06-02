# Kryptografické Laboratorium

Edukační webová aplikace demonstrující dva kryptografické algoritmy — klasický **Hill Cipher** a post-kvantový **ML-KEM (Kyber)**. Každý algoritmus má interaktivní UI se šifrováním, dešifrováním a filtrovanou historií operací.

## Architektura

```
browser  ──fetch──▶  api.php  ──proc_open──▶  cipher_wrapper.py
                        │                          │
                      db.php                cypher_py_modules/
                        │                    hill_cypher.py
                      MariaDB                ml_kem.py
```

| Soubor | Role |
|--------|------|
| `index.php` | Úvodní stránka, navigace |
| `hill.php` / `mlkem.php` | Stránky šifer (HTML + filter UI) |
| `crypto.js` | Sdílená JS logika — encrypt/decrypt/filter (DRY) |
| `api.php` | REST backend — volá Python, loguje do DB |
| `db.php` | PDO připojení k MariaDB |
| `cipher_wrapper.py` | Python vstupní bod — stdin/stdout JSON bridge |
| `cypher_py_modules/hill_cypher.py` | Hill Cipher implementace (numpy + sympy) |
| `cypher_py_modules/ml_kem.py` | ML-KEM 512 implementace |
| `init.sql` | Schema tabulky `history` |
| `docker-compose.yml` | PHP/Apache + MariaDB stack |
| `Dockerfile` | PHP 8.2 + Apache + Python 3 + numpy + sympy |

## Spuštění

```bash
docker compose up --build
```

Aplikace běží na [http://localhost](http://localhost).

Při prvním spuštění MariaDB inicializuje `init.sql` automaticky.

## Jak to funguje

### Šifrování (obecně)
1. Uživatel zadá text → klikne Šifrovat
2. `crypto.js` → POST `api.php` s `{ operation: "hill_enc"|"mlkem_enc", input }`
3. `api.php` → `cipher_wrapper.py` přes `proc_open` (stdin JSON → stdout JSON)
4. Výsledek uložen do `history` tabulky + vrácen do UI
5. `loadHistory()` načte záznamy, `applyFilter()` zobrazí aktuální výběr

### Dešifrování
- Enc záznamy v tabulce mají tlačítko **Dešifrovat**
- Klíč (matice / KEM klíče) je uložen v DB sloupci `cipher_key`
- Dec záznam se uloží s `parent_id` odkazujícím na enc rodič

### Filtrování (client-side)
- `allRecords` — plný seznam, načten jednou při `loadHistory()`
- Filtry: **typ** (enc/dec) + **čas** (24h / 7 dní)
- `applyFilter()` filtruje `allRecords` bez extra API požadavků

## Hill Cipher

Polyalfabetická maticová šifra:

```
C = (K × P) mod n    # šifrování
P = (K⁻¹ × C) mod n # dešifrování
```

- Abeceda: 43 znaků (ASCII + česká diakritika)
- Klíč: náhodná invertibilní matice 2×2 (mod 43)
- Odolný vůči frekvenční analýze, zranitelný known-plaintext útokem

## ML-KEM (Kyber)

Post-kvantový KEM, standardizován NIST 2024 jako FIPS 203:

```
keygen()      → (vk, pk)   # veřejný + privátní klíč
encaps(vk)    → (K, c)     # sdílený klíč + KEM ciphertext
decaps(pk, c) → K           # příjemce obnoví K
```

- Text šifrován SHAKE-256 stream cipher klíčem K
- Bezpečnost = LWE problém na modulárních mřížích → kvantově odolné
- Fujisaki-Okamoto transformace: neplatný ciphertext → fake klíč (nezjistitelné selhání)

## Databáze

Jediná tabulka `history`:

| Sloupec | Typ | Popis |
|---------|-----|-------|
| `id` | INT AI PK | — |
| `cipher_type` | VARCHAR(20) | `hill` nebo `mlkem` |
| `typ_operace` | VARCHAR(10) | `enc` nebo `dec` |
| `cipher_key` | TEXT | JSON klíče (pouze enc záznamy) |
| `input` | TEXT | Vstupní text |
| `output` | TEXT | Výstupní text |
| `parent_id` | INT NULL | Odkaz na enc rodič (jen dec záznamy) |
| `timestamp` | TIMESTAMP | Auto |

## Poznámka

Aplikace je **pouze pro vzdělávací účely**. Implementace nejsou vhodné pro produkční nasazení.
