<?php
function trunc(string $s, int $len): string {
    return mb_strlen($s) > $len ? mb_substr($s, 0, $len) . '…' : $s;
}

function renderHistoryRow(array $r, bool $isDecrypted = false): string {
    $isEnc      = $r['typ_operace'] === 'enc';
    $rowClass   = $isEnc ? 'enc-row' : 'dec-row';
    $opLabel    = $isEnc ? '🔒 enc' : '└ 🔓 dec';
    $btn = '';
    if ($isEnc) {
        $btn = $isDecrypted
            ? '<span class="decrypted-badge">✓ Dešifrováno</span>'
            : '<button class="btn-decrypt" onclick="decrypt(' . (int)$r['id'] . ')">🔓 Dešifrovat</button>';
    }
    $inFull  = htmlspecialchars((string)($r['input']  ?? ''), ENT_QUOTES);
    $outFull = htmlspecialchars((string)($r['output'] ?? ''), ENT_QUOTES);
    $outClass = 'cell-copy' . ($r['parent_id'] ? ' plaintext-result' : '');
    return '<tr class="' . $rowClass . '">'
        . '<td>' . $opLabel . '</td>'
        . '<td class="cell-copy" data-full="' . $inFull . '" title="Kliknout = kopírovat">'
        .     htmlspecialchars(trunc((string)($r['input']  ?? ''), 45)) . '</td>'
        . '<td class="' . $outClass . '" data-full="' . $outFull . '" title="Kliknout = kopírovat">'
        .     htmlspecialchars(trunc((string)($r['output'] ?? ''), 45)) . '</td>'
        . '<td>' . htmlspecialchars($r['timestamp']) . '</td>'
        . '<td>' . $btn . '</td>'
        . '</tr>';
}
