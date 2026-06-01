<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ML-KEM - Kryptografické Šifry</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header>
        <h1>🔐 ML-KEM (Moderní Post-Kvantová Šifra)</h1>
        <nav>
            <a href="index.php">← Domů</a>
            <a href="hill.php">← Hill Cipher</a>
        </nav>
    </header>

    <div class="container">
        <main>
            <h2>ML-KEM Demonstrace</h2>

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

            <!-- History Table -->
            <div class="history-section">
                <h2>Operace Historie</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Operace</th>
                            <th>Vstup</th>
                            <th>Výstup</th>
                            <th>Čas</th>
                        </tr>
                    </thead>
                    <tbody id="historyBody">
                        <tr>
                            <td colspan="4" style="text-align: center; color: #999;">
                                Zatím žádné záznamy. Proveďte operaci.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="info-box">
                <h4>🌐 Co je ML-KEM (Kyber)?</h4>
                <p>
                    <strong>ML-KEM</strong> (Module-Lattice-Based Key-Encapsulation Mechanism) je moderní
                    post-kvantový šifrovací algoritmus, který se používá pro bezpečnou výměnu klíčů.
                </p>
                <p style="margin-top: 10px;">
                    <strong>Základní Princip:</strong><br>
                    Bezpečnost je založena na problému &quot;Learning With Errors&quot; (LWE) na modulárních mřížích,
                    které jsou odolné vůči útokům kvantových počítačů.
                </p>
                <p style="margin-top: 10px;">
                    <strong>Klíčové Vlastnosti:</strong><br>
                    - Odolnost vůči kvantovým počítačům<br>
                    - Efektivní na klasických počítačích<br>
                    - Malé velikosti klíčů a šifrových textů<br>
                    - Standardizován NIST (2024)
                </p>
                <p style="margin-top: 10px;">
                    <strong>Matematika:</strong><br>
                    Bezpečnost se opírá o tvrdost polynomiálních rovnic nad módulo q.
                    Generování klíčů vyžaduje náhodné polynomy a matice.
                </p>
                <p style="margin-top: 10px;">
                    <strong>Aplikace:</strong><br>
                    - Budoucí bezpečná komunikace<br>
                    - Ochrana před kvantovými hrozbami<br>
                    - TLS/SSL komunikace<br>
                    - Digitální podpisy (via ML-DSA)
                </p>
                <p style="margin-top: 10px;">
                    <strong>Srovnání s RSA:</strong><br>
                    RSA: Bezpečnost = faktorizace velkých čísel (ohroženo kvantovými počítači)<br>
                    ML-KEM: Bezpečnost = problém LWE (předpokládá se odolný i vůči kvantovým počítačům)
                </p>
            </div>
        </main>
    </div>

    <footer>
        <p>© 2025 Kryptografické Laboratorium</p>
    </footer>

    <script>
        // Load history from database on page load
        function loadHistory() {
            fetch('api.php?action=getHistory')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('historyBody');
                    if (data.records && data.records.length > 0) {
                        tbody.innerHTML = '';
                        data.records.forEach(record => {
                            const row = document.createElement('tr');
                            row.innerHTML = `
                                <td>${record.typ_operace}</td>
                                <td class="clickable" onclick="copyToInput('${escapeHtml(record.input)}')">
                                    ${truncate(record.input, 30)}
                                </td>
                                <td class="clickable" onclick="copyToInput('${escapeHtml(record.output)}')">
                                    ${truncate(record.output, 30)}
                                </td>
                                <td>${record.timestamp}</td>
                            `;
                            tbody.appendChild(row);
                        });
                    }
                })
                .catch(error => console.error('Error loading history:', error));
        }

        // Copy text from table to input field
        function copyToInput(text) {
            document.getElementById('input').value = text;
            document.getElementById('status').innerHTML = '<div class="success">✓ Text zkopírován do vstupního pole</div>';
            setTimeout(() => { document.getElementById('status').innerHTML = ''; }, 3000);
        }

        // Escape HTML special characters
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Truncate long text
        function truncate(text, length) {
            return text.length > length ? text.substring(0, length) + '...' : text;
        }

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
                    loadHistory();
                } else {
                    status.innerHTML = '<div class="error">❌ ' + data.error + '</div>';
                }
            } catch (error) {
                status.innerHTML = '<div class="error">❌ Chyba: ' + error.message + '</div>';
            }
        }

        function encrypt() {
            sendRequest('mlkem_enc');
        }

        function decrypt() {
            sendRequest('mlkem_dec');
        }

        // Load history when page loads
        document.addEventListener('DOMContentLoaded', loadHistory);

        // Clear status message after delay
        setInterval(() => {
            const status = document.getElementById('status');
            if (status.innerHTML !== '') {
                setTimeout(() => { status.innerHTML = ''; }, 5000);
            }
        }, 100);
    </script>
</body>
</html>
