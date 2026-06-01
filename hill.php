<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hill Cipher - Kryptografické Šifry</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>📐 Hill Cipher (Hillova Šifra)</h1>
        <nav>
            <a href="index.php">← Domů</a>
            <a href="mlkem.php">ML-KEM →</a>
        </nav>
    </header>

    <div class="container">
        <main>
            <h2>Hill Cipher Demonstrace</h2>

            <div class="form-group">
                <label for="input">Vstup (text k zašifrování/dešifrování):</label>
                <textarea id="input" placeholder="Zadejte text zde..."></textarea>
            </div>

            <div class="button-group">
                <button onclick="encrypt()">🔒 Šifrovat</button>
                <button onclick="decrypt()">🔓 Dešifrovat</button>
            </div>

            <div id="status"></div>

            <label class="output-label" for="output">Výstup:</label>
            <div id="output">Výsledek se zobrazí zde...</div>

            <div class="info-box">
                <h4>🧮 Jak Funguje Hill Cipher?</h4>
                <p>
                    <strong>Hill Cipher</strong> je polyalphabetická šifra využívající maticové operace.
                </p>
                <p style="margin-top: 10px;">
                    <strong>Základní Princip:</strong><br>
                    Text je rozdělen do bloků a každý blok je reprezentován jako vektor.
                    Tento vektor je vynásoben šifrovací maticí (klíč) modulo 26.
                </p>
                <p style="margin-top: 10px;">
                    <strong>Matematika:</strong><br>
                    <code>C = (K × P) mod 26</code><br>
                    Kde: <code>C</code> = šifrový text, <code>K</code> = matice klíče, <code>P</code> = text
                </p>
                <p style="margin-top: 10px;">
                    <strong>Dešifrování:</strong><br>
                    Dešifrování používá inverzní matici: <code>P = (K⁻¹ × C) mod 26</code>
                </p>
                <p style="margin-top: 10px;">
                    <strong>Výhody:</strong><br>
                    - Odolnost vůči frekvenční analýze<br>
                    - Polyalphabetické zašifrování<br>
                    - Matematicky elegantní řešení
                </p>
                <p style="margin-top: 10px;">
                    <strong>Nevýhody:</strong><br>
                    - Náročné na výpočty bez počítače<br>
                    - Potřeba invertibilní matice<br>
                    - Náchylná na útok se známým textem
                </p>
            </div>
        </main>
    </div>

    <footer>
        <p>© 2025 Kryptografické Laboratorium</p>
    </footer>

    <script>
        // Send AJAX request to API
        async function sendRequest(operation) {
            const input = document.getElementById('input').value;
            const output = document.getElementById('output');
            const status = document.getElementById('status');

            if (!input.trim()) {
                status.innerHTML = '<div class="error">❌ Chyba: Zadejte text do vstupního pole</div>';
                return;
            }

            status.innerHTML = '<div class="success">⏳ Zpracování...</div>';

            try {
                const response = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        operation: operation,
                        input: input
                    })
                });

                const data = await response.json();

                if (data.success) {
                    output.textContent = data.output;
                    status.innerHTML = '<div class="success">✓ Operace úspěšná</div>';
                } else {
                    status.innerHTML = '<div class="error">❌ ' + data.error + '</div>';
                }
            } catch (error) {
                status.innerHTML = '<div class="error">❌ Chyba: ' + error.message + '</div>';
            }
        }

        function encrypt() {
            sendRequest('hill_enc');
        }

        function decrypt() {
            sendRequest('hill_dec');
        }

        // Clear status after 5 seconds
        setInterval(() => {
            const status = document.getElementById('status');
            if (status.innerHTML !== '') {
                setTimeout(() => { status.innerHTML = ''; }, 5000);
            }
        }, 100);
    </script>
</body>
</html>
