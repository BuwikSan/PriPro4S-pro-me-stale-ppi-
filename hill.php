<?php
$cipher_type = 'hill';
try {
    require_once 'src/db.php';
    require_once 'src/render_history.php';
    $stmt = $pdo->prepare(
        'SELECT id, typ_operace, input, output, cipher_key, parent_id, timestamp
         FROM history WHERE cipher_type = ?
         ORDER BY COALESCE(parent_id, id) DESC, id ASC LIMIT 100'
    );
    $stmt->execute([$cipher_type]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $decryptedIds = array_fill_keys(array_filter(array_column($records, 'parent_id')), true);
} catch (Throwable $e) {
    $records = [];
    $decryptedIds = [];
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hill Cipher – Kryptografické Šifry</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <h1>Hill Cipher (Hillova Šifra)</h1>
        <nav>
            <a href="index.php">← Domů</a>
            <a href="mlkem.php">ML-KEM →</a>
        </nav>
    </header>

    <div class="container">
        <main>
            <h2>Hill Cipher Demonstrace</h2>

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
                            <th style="width:120px">Čas</th>
                            <th style="width:130px">Akce</th>
                        </tr>
                    </thead>
                    <tbody id="historyBody">
                        <?php if (empty($records)): ?>
                            <tr><td colspan="5" class="empty-row">Zatím žádné záznamy.</td></tr>
                        <?php else: foreach ($records as $r):
                            echo renderHistoryRow($r, $r['typ_operace'] === 'enc' && isset($decryptedIds[$r['id']]));
                        endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="info-box">
                <h4>🧮 Jak Funguje Hill Cipher?</h4>
                <p><strong>Hill Cipher</strong> je polyalphabetická šifra využívající maticové operace.</p>
                <p style="margin-top:10px;">
                    <strong>Matematika:</strong><br>
                    <code>C = (K × P) mod n</code> — šifrování<br>
                    <code>P = (K⁻¹ × C) mod n</code> — dešifrování inverzní maticí<br>
                    kde <code>n</code> = velikost abecedy (zde 43 znaků vč. české diakritiky)
                </p>
                <p style="margin-top:10px;">
                    <strong>Výhody:</strong> odolnost vůči frekvenční analýze<br>
                    <strong>Nevýhody:</strong> náchylná na útok se známým plaintextem
                </p>
            </div>
        </main>
    </div>

    <footer><p>© 2025 Kryptografické Laboratorium</p></footer>

    <script>
        const CIPHER_TYPE = 'hill';
        const INITIAL_RECORDS = <?= json_encode($records) ?>;
    </script>
    <script src="assets/js/crypto.js"></script>
</body>
</html>
