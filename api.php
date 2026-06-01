<?php
// API handler for crypto operations
header('Content-Type: application/json');

// Load database connection
require_once 'db.php';

// Handle history retrieval (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'getHistory') {
    try {
        $stmt = $pdo->query('SELECT typ_operace, input, output, timestamp FROM history ORDER BY id DESC LIMIT 50');
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['records' => $records]);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Chyba při čtení historie: ' . $e->getMessage()]);
    }
    exit;
}

// Handle POST requests (encrypt/decrypt operations)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!isset($input['operation']) || !isset($input['input'])) {
        echo json_encode(['success' => false, 'error' => 'Chybějící parametry']);
        exit;
    }

    $operation = $input['operation'];
    $text = $input['input'];

    // Route to appropriate function
    switch ($operation) {
        case 'hill_enc':
            $result = encryptHill($text);
            break;
        case 'hill_dec':
            $result = decryptHill($text);
            break;
        case 'mlkem_enc':
            $result = encryptMLKEM($text);
            logHistory('mlkem_enc', $text, $result);
            break;
        case 'mlkem_dec':
            $result = decryptMLKEM($text);
            logHistory('mlkem_dec', $text, $result);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Neznámá operace']);
            exit;
    }

    // Return success response
    echo json_encode(['success' => true, 'output' => $result]);
    exit;
}

// Stub: Encrypt using Hill Cipher (placeholder for Python implementation)
function encryptHill($text) {
    // Mock: Return base64-encoded version with prefix
    return '[Hill Encrypted] ' . base64_encode($text);
}

// Stub: Decrypt using Hill Cipher (placeholder for Python implementation)
function decryptHill($text) {
    // Mock: Decode if it looks like our encrypted format
    if (strpos($text, '[Hill Encrypted] ') === 0) {
        return base64_decode(substr($text, 17));
    }
    return '[Hill Decryption Error] Vstup není ve správném formátu';
}

// Stub: Encrypt using ML-KEM (placeholder for Python implementation)
function encryptMLKEM($text) {
    // Mock: Return base64-encoded version with prefix
    return '[ML-KEM Encrypted] ' . base64_encode($text);
}

// Stub: Decrypt using ML-KEM (placeholder for Python implementation)
function decryptMLKEM($text) {
    // Mock: Decode if it looks like our encrypted format
    if (strpos($text, '[ML-KEM Encrypted] ') === 0) {
        return base64_decode(substr($text, 19));
    }
    return '[ML-KEM Decryption Error] Vstup není ve správném formátu';
}

// Log operation to database
function logHistory($operation, $input, $output) {
    global $pdo;
    try {
        $stmt = $pdo->prepare('INSERT INTO history (typ_operace, input, output) VALUES (?, ?, ?)');
        $stmt->execute([$operation, substr($input, 0, 500), substr($output, 0, 500)]);
    } catch (Exception $e) {
        // Silently fail - don't crash the app if logging fails
        error_log('Database logging error: ' . $e->getMessage());
    }
}

// Fallback: Invalid request
echo json_encode(['success' => false, 'error' => 'Neplatný požadavek']);
