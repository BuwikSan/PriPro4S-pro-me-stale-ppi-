<?php
ob_start();
header('Content-Type: application/json');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => "PHP Error [$errno]: $errstr in $errfile:$errline"]);
    exit(1);
});

try {
    require_once 'db.php';
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit(1);
}

// GET: vrátí historii — seřazeno tak, že dec záznamy jsou hned za svým enc rodičem
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

// POST: crypto operace
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $req       = json_decode(file_get_contents('php://input'), true);
    $op        = $req['operation']  ?? '';
    $text      = $req['input']      ?? '';
    $ckey      = $req['cipher_key'] ?? '';
    $parent_id = isset($req['parent_id']) ? (int)$req['parent_id'] : null;

    if (!$op || !$text) {
        echo json_encode(['success' => false, 'error' => 'Chybějící parametry']);
        exit;
    }

    try { switch ($op) {

        case 'hill_enc':
            $py = callPython(['operation' => 'hill_enc', 'text' => $text]);
            if ($py['success']) {
                logHistory('hill', 'enc', json_encode($py['keys_data']), $text, $py['ciphertext']);
                echo json_encode(['success' => true, 'output' => $py['ciphertext']]);
            } else { echo json_encode($py); }
            break;

        case 'hill_dec':
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
            break;

        case 'mlkem_enc':
            $py = callPython(['operation' => 'mlkem_enc', 'text' => $text]);
            if ($py['success']) {
                $ckey_json = json_encode(['pk' => $py['pk'], 'c_kem' => $py['c_kem']]);
                logHistory('mlkem', 'enc', $ckey_json, $text, $py['ct']);
                echo json_encode(['success' => true, 'output' => $py['ct']]);
            } else { echo json_encode($py); }
            break;

        case 'mlkem_dec':
            $kd = json_decode($ckey, true);
            if (!$kd || empty($kd['pk']) || empty($kd['c_kem'])) {
                echo json_encode(['success' => false, 'error' => 'Chybí klíč pro dešifrování.']);
                break;
            }
            $py = callPython(['operation' => 'mlkem_dec', 'ct' => $text, 'pk' => $kd['pk'], 'c_kem' => $kd['c_kem']]);
            if ($py['success']) {
                logHistory('mlkem', 'dec', null, $text, $py['plaintext'], $parent_id);
                echo json_encode(['success' => true, 'output' => $py['plaintext']]);
            } else { echo json_encode($py); }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Neznámá operace']);

    } } catch (Throwable $e) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Server: ' . $e->getMessage()]);
    }
    exit;
}

function callPython(array $data): array {
    $pipes = [];
    $proc = proc_open('python3 /var/www/html/cipher_wrapper.py', [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ], $pipes);
    if (!is_resource($proc)) return ['success' => false, 'error' => 'Nelze spustit Python'];
    fwrite($pipes[0], json_encode($data));
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($proc);
    if ($err) return ['success' => false, 'error' => 'Python: ' . trim($err)];
    return json_decode($out, true) ?: ['success' => false, 'error' => 'Špatná odpověď'];
}

function logHistory(string $cipher_type, string $typ_operace, ?string $cipher_key, string $input, string $output, ?int $parent_id = null): void {
    global $pdo;
    $stmt = $pdo->prepare(
        'INSERT INTO history (cipher_type, typ_operace, cipher_key, input, output, parent_id) VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$cipher_type, $typ_operace, $cipher_key, $input, $output, $parent_id]);
}

echo json_encode(['success' => false, 'error' => 'Neplatný požadavek']);
