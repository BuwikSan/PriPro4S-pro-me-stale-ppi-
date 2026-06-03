# Dokumentace projektu — Kryptografické Šifry

Tato složka obsahuje **kompletní vysvětlení** všech PHP modulů, Docker souborů a použitých
technologií. Cílem je, abys porozuměl každému řádku natolik, že v něm dokážeš dělat libovolné změny.

> Python kód (`python/cipher_wrapper.py`, `python/cypher_py_modules/`) zde **není** dokumentován — ten už umíš.
> Zmiňuje se jen tam, kde je nutné popsat, jak na něj PHP navazuje.

## Co je to za projekt?

Webová edukační aplikace demonstrující dvě šifry:

1. **Hill Cipher** — klasická maticová šifra
2. **ML-KEM** — moderní post-kvantový algoritmus (NIST FIPS 203)

Uživatel zadá text v prohlížeči → text se zašifruje → uloží do databáze → zobrazí v historii →
z historie lze kliknutím dešifrovat zpět.

## Architektura — tok dat

```
┌─────────────┐   HTTP/JSON    ┌──────────┐   STDIN/STDOUT JSON   ┌────────────────────┐
│  Prohlížeč  │ ─────────────► │  api.php │ ────────────────────► │ cipher_wrapper.py  │
│ (crypto.js) │ ◄───────────── │  (PHP)   │ ◄──────────────────── │   (Python šifry)   │
└─────────────┘                └────┬─────┘                       └────────────────────┘
                                    │ PDO (SQL)
                                    ▼
                              ┌──────────┐
                              │ MariaDB  │  tabulka `history`
                              └──────────┘
```

- **Frontend**: `index.php`, `hill.php`, `mlkem.php` (HTML) + `assets/js/crypto.js` (logika) + `assets/css/style.css` (vzhled)
- **Backend API**: `src/api.php` (router) + `src/db.php` (DB) + `src/render_history.php` (PHP render helper)
- **Šifrovací jádro**: `python/cipher_wrapper.py` + `python/cypher_py_modules/` (mimo rozsah)
- **Databáze**: MariaDB, schéma v `database/init.sql`
- **Běhové prostředí**: Docker — `Dockerfile` + `docker-compose.yml`

## Struktura projektu

```
/
├── index.php, hill.php, mlkem.php   ← stránky (čisté URL na rootu)
├── src/                             ← PHP backend
│   ├── api.php
│   ├── db.php
│   └── render_history.php
├── assets/
│   ├── css/style.css
│   └── js/crypto.js
├── python/
│   ├── cipher_wrapper.py
│   └── cypher_py_modules/
├── database/
│   └── init.sql
├── docs/
├── Dockerfile
└── docker-compose.yml
```

## Obsah dokumentace

### PHP moduly
- [php/api.md](php/api.md) — REST API router, `runEnc`/`runDec` helpers, volání Pythonu, logování
- [php/db.md](php/db.md) — připojení k databázi přes PDO, retry logika
- [php/render_history.md](php/render_history.md) — `render_history.php`: sdílené PHP renderování řádků tabulky
- [php/stranky.md](php/stranky.md) — `index.php`, `hill.php`, `mlkem.php` + `crypto.js`

### Docker a databáze
- [docker/Dockerfile.md](docker/Dockerfile.md) — sestavení web image (PHP + Apache + Python)
- [docker/docker-compose.md](docker/docker-compose.md) — orchestrace web + DB kontejnerů
- [docker/init-sql.md](docker/init-sql.md) — schéma databáze

### Základy technologií
- [zaklady-technologii.md](zaklady-technologii.md) — **velký přehled** všech jazyků a technologií
  (PHP, HTML, CSS, JavaScript, HTTP/REST, JSON, SQL/MariaDB, Docker) s odkazy na tvůj kód

## Doporučené pořadí čtení

1. Začni [zaklady-technologii.md](zaklady-technologii.md) — pochopíš, co je co.
2. [docker/docker-compose.md](docker/docker-compose.md) — jak se to celé spustí.
3. [php/db.md](php/db.md) → [php/api.md](php/api.md) — backend tok.
4. [php/stranky.md](php/stranky.md) — frontend.
