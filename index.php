<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kryptografické Šifry</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>⚡ KRYPTOGRAFICKÉ ŠIFRY ⚡</h1>
        <p>Demografie a edukace moderních šifrovacích algoritmů</p>
    </header>

    <div class="container">
        <main>
            <h2>Vítejte v Kryptografickém Laboratoriu</h2>

            <div class="info-box">
                <h4>O Projektu</h4>
                <p>
                    Tato aplikace demonstruje funkci dvou klasických a moderních kryptografických algoritmů.
                    Každá šifra má interaktivní rozhraní s vysvětlením matematických principů.
                </p>
                <p style="margin-top: 15px;">
                    <strong>Cíl:</strong> Pochopit, jak funguje šifrování a jak se lze bránit moderním hrozbám.
                </p>
            </div>

            <h3>Dostupné Šifry</h3>

            <nav style="margin-top: 30px; display: flex; flex-direction: column; gap: 15px;">
                <a href="hill.php" style="display: block; text-align: center; padding: 15px; border: 2px solid #00ff66; text-decoration: none; color: #00ff66; font-weight: bold; transition: all 0.3s;">
                    Hill Cipher (Hillova Šifra)
                </a>
                <a href="mlkem.php" style="display: block; text-align: center; padding: 15px; border: 2px solid #00e5ff; text-decoration: none; color: #00e5ff; font-weight: bold; transition: all 0.3s;">
                    ML-KEM (Moderní Post-Kvantová Šifra)
                </a>
            </nav>

            <div class="info-box" style="margin-top: 40px;">
                <h4>Jak Používat</h4>
                <p>
                    1. Vyberte jednu z šifer výše<br>
                    2. Zadejte text do vstupního pole<br>
                    3. Klikněte na "Šifrovat"<br>
                    4. Výsledek se zobrazí v reálném čase v tabulce<br>
                    5. V tabulce klikněte na dešifrovat<br>
                    6. Za pomocí stejného klíče se zpráva dešifruje<br>
                    7. Prozkoumejte detaily šifer níže
                </p>
            </div>

            <div class="info-box" style="margin-top: 30px; background-color: #0a1a1a; border-color: #00e5ff;">
                <h4 style="color: #00e5ff;">Poznámka</h4>
                <p>
                    Tato aplikace je určena <strong>pouze pro vzdělávací účely</strong>.
                    Šifry jsou demonstrační a nejsou vhodné pro skutečné bezpečnostní aplikace.
                </p>
            </div>
        </main>
    </div>

    <footer>
        <p>© 2025 Kryptografické Laboratorium Kamen-industries | Vytvořeno pro vzdělávací účely</p>
    </footer>
</body>
</html>
