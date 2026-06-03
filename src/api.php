<?php
ob_start();
header('Content-Type: application/json');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => "PHP Error [$errno]: $errstr in $errfile:$errline"]);
    exit(1);
});

try {
    require_once __DIR__ . '/db.php';
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
            runEnc('hill', ['operation' => 'hill_enc', 'text' => $text], $text,
                fn($py) => [$py['ciphertext'], json_encode($py['keys_data'])]
            );
            break;

        case 'hill_dec':
            $keys_data = json_decode($ckey, true);
            if (!$keys_data || empty($keys_data['keys'])) {
                echo json_encode(['success' => false, 'error' => 'Chybí klíč pro dešifrování.']);
                break;
            }
            runDec('hill', ['operation' => 'hill_dec', 'text' => $text, 'keys_data' => $keys_data], $text, $parent_id);
            break;

        case 'mlkem_enc':
            runEnc('mlkem', ['operation' => 'mlkem_enc', 'text' => $text], $text,
                fn($py) => [$py['ct'], json_encode(['pk' => $py['pk'], 'c_kem' => $py['c_kem']])]
            );
            break;

        case 'mlkem_dec':
            $kd = json_decode($ckey, true);
            if (!$kd || empty($kd['pk']) || empty($kd['c_kem'])) {
                echo json_encode(['success' => false, 'error' => 'Chybí klíč pro dešifrování.']);
                break;
            }
            runDec('mlkem', ['operation' => 'mlkem_dec', 'ct' => $text, 'pk' => $kd['pk'], 'c_kem' => $kd['c_kem']], $text, $parent_id);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Neznámá operace']);

    } } catch (Throwable $e) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Server: ' . $e->getMessage()]);
    }
    exit;
}

function runEnc(string $cipher_type, array $py_payload, string $text, callable $extract): void {
    $py = callPython($py_payload);
    if (!$py['success']) { echo json_encode($py); return; }
    [$output, $cipher_key] = $extract($py);
    logHistory($cipher_type, 'enc', $cipher_key, $text, $output);
    echo json_encode(['success' => true, 'output' => $output]);
}

function runDec(string $cipher_type, array $py_payload, string $text, ?int $parent_id): void {
    $py = callPython($py_payload);
    if (!$py['success']) { echo json_encode($py); return; }
    logHistory($cipher_type, 'dec', null, $text, $py['plaintext'], $parent_id);
    echo json_encode(['success' => true, 'output' => $py['plaintext']]);
}

function callPython(array $data): array {
    $pipes = [];
    $proc = proc_open('python3 /var/www/html/python/cipher_wrapper.py', [
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
