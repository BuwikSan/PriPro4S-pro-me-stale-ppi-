<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ML-KEM – Kryptografické Šifry</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>ML-KEM (Post-Kvantová Šifra)</h1>
        <nav>
            <a href="index.php">← Domů</a>
            <a href="hill.php">← Hill Cipher</a>
        </nav>
    </header>

    <div class="container">
        <main>
            <h2>ML-KEM Demonstrace</h2>

            <!-- Pouze šifrování — decrypt je v tabulce -->
            <div class="form-group">
                <label for="input">Vstupní text:</label>
                <textarea id="input" placeholder="Zadej text ke zašifrování..."></textarea>
            </div>
            <div class="button-group">
                <button onclick="encrypt()">🔒 Šifrovat</button>
            </div>
            <div id="status"></div>

            <!-- Tabulka historie: enc řádky mají tlačítko Dešifrovat -->
            <div class="history-section">
                <h2>Historie operací</h2>
                <div class="filter-bar">
                    <select id="filterType" onchange="applyFilter()">
                        <option value="all">Všechny operace</option>
                        <option value="enc">Jen šifrování</option>
                        <option value="dec">Jen dešifrování</option>
                    </select>
                    <select id="filterTime" onchange="applyFilter()">
                        <option value="all">Celá historie</option>
                        <option value="today">Posledních 24h</option>
                        <option value="week">Posledních 7 dní</option>
                    </select>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th style="width:90px">Operace</th>
                            <th>Vstup</th>
                            <th>Výstup</th>
                            <th style="width:140px">Čas</th>
                            <th style="width:110px">Akce</th>
                        </tr>
                    </thead>
                    <tbody id="historyBody">
                        <tr><td colspan="5" class="empty-row">Zatím žádné záznamy.</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="info-box">
                <h4>🌐 Co je ML-KEM (Kyber)?</h4>
                <p>
                    <strong>ML-KEM</strong> (Module-Lattice-Based Key-Encapsulation Mechanism) —
                    post-kvantový KEM. Standardizován NIST 2024 (FIPS 203).
                </p>
                <p style="margin-top:10px;">
                    <strong>Princip KEM:</strong><br>
                    <code>keygen()</code> → (vk, pk) — veřejný + privátní klíč<br>
                    <code>encaps(vk)</code> → (K, c) — sdílený klíč K + ciphertext c<br>
                    <code>decaps(pk, c)</code> → K — příjemce obnoví K<br>
                    Text se šifruje pomocí SHAKE-256 stream cipher s klíčem K.
                </p>
                <p style="margin-top:10px;">
                    <strong>Proč post-kvantové?</strong><br>
                    RSA/ECC bezpečnost = faktorizace / diskrétní logaritmus → zlomitelné Shorovým algoritmem.<br>
                    ML-KEM = LWE problém na modulárních mřížích → kvantově odolné.
                </p>
                <p style="margin-top:10px;">
                    <strong>Fujisaki-Okamoto:</strong>
                    Při neplatném ciphertextu vrátí fake klíč J(z,c) — útočník nepozná selhání.
                </p>
            </div>
        </main>
    </div>

    <footer><p>© 2025 Kryptografické Laboratorium</p></footer>

    <script>const CIPHER_TYPE = 'mlkem';</script>
    <script src="crypto.js"></script>
</body>
</html>
