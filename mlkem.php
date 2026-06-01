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
        <h1>🔐 ML-KEM (Post-Kvantová Šifra)</h1>
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

    <script>
        let historyData = {};

        function loadHistory() {
            fetch('api.php?action=getHistory&cipher_type=mlkem')
                .then(r => r.json())
                .then(data => {
                    historyData = {};
                    const tbody = document.getElementById('historyBody');
                    if (!data.records || data.records.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="empty-row">Žádné záznamy.</td></tr>';
                        return;
                    }
                    data.records.forEach(r => { historyData[r.id] = r; });
                    tbody.innerHTML = data.records.map(r => renderRow(r)).join('');
                });
        }

        function renderRow(r) {
            const isEnc   = r.typ_operace === 'enc';
            const isChild = r.parent_id !== null && r.parent_id !== '0';
            const rowClass = isEnc ? 'enc-row' : 'dec-row';
            const opLabel  = isEnc ? '🔒 enc' : '└ 🔓 dec';
            const btn      = isEnc
                ? `<button class="btn-decrypt" onclick="decrypt(${r.id})">🔓 Dešifrovat</button>`
                : '';
            return `<tr class="${rowClass}">
                <td>${opLabel}</td>
                <td>${truncate(r.input,  45)}</td>
                <td class="${isChild ? 'plaintext-result' : ''}">${truncate(r.output, 45)}</td>
                <td>${r.timestamp}</td>
                <td>${btn}</td>
            </tr>`;
        }

        async function decrypt(id) {
            const r = historyData[id];
            setStatus('⏳ Dešifrování...', 'success');
            try {
                const resp = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        operation:  'mlkem_dec',
                        input:      r.output,      // ct (base64)
                        cipher_key: r.cipher_key,  // {pk, c_kem}
                        parent_id:  r.id
                    })
                });
                const data = await resp.json();
                if (data.success) {
                    setStatus('✓ Dešifrováno – viz tabulka', 'success');
                    loadHistory();
                } else {
                    setStatus('❌ ' + data.error, 'error');
                }
            } catch (e) {
                setStatus('❌ ' + e.message, 'error');
            }
        }

        async function encrypt() {
            const text = document.getElementById('input').value.trim();
            if (!text) { setStatus('❌ Zadej text', 'error'); return; }

            setStatus('⏳ Šifrování...', 'success');
            try {
                const resp = await fetch('api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ operation: 'mlkem_enc', input: text })
                });
                const data = await resp.json();
                if (data.success) {
                    document.getElementById('input').value = '';
                    setStatus('✓ Zašifrováno – viz tabulka', 'success');
                    loadHistory();
                } else {
                    setStatus('❌ ' + data.error, 'error');
                }
            } catch (e) {
                setStatus('❌ ' + e.message, 'error');
            }
        }

        function truncate(text, len) {
            if (!text) return '';
            return text.length > len ? text.substring(0, len) + '…' : text;
        }

        function setStatus(msg, type) {
            document.getElementById('status').innerHTML = `<div class="${type}">${msg}</div>`;
            setTimeout(() => { document.getElementById('status').innerHTML = ''; }, 4000);
        }

        document.addEventListener('DOMContentLoaded', loadHistory);
    </script>
</body>
</html>
