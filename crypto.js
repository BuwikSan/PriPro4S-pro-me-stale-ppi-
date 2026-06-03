// Shared logic for hill.php and mlkem.php
// Each page sets: const CIPHER_TYPE = 'hill' | 'mlkem'
// Each page sets: const INITIAL_RECORDS = [...] (server-rendered by PHP on page load)

let historyData = {};
let allRecords = [];  // plný seznam pro client-side filtrování

function loadHistory() {
    fetch(`api.php?action=getHistory&cipher_type=${CIPHER_TYPE}`)
        .then(r => r.json())
        .then(data => {
            historyData = {};
            allRecords = data.records || [];
            allRecords.forEach(r => { historyData[r.id] = r; });
            applyFilter();
        });
}

// Aplikuj vybrané filtry na allRecords a překresli tabulku
function applyFilter() {
    const typeFilter = document.getElementById('filterType').value; // all/enc/dec
    const timeFilter = document.getElementById('filterTime').value; // all/today/week

    const now = new Date();
    const filtered = allRecords.filter(r => {
        if (typeFilter !== 'all' && r.typ_operace !== typeFilter) return false;
        if (timeFilter !== 'all') {
            const hoursAgo = (now - new Date(r.timestamp.replace(' ', 'T'))) / 3600000;
            if (timeFilter === 'today' && hoursAgo > 24) return false;
            if (timeFilter === 'week' && hoursAgo > 168) return false;
        }
        return true;
    });

    const tbody = document.getElementById('historyBody');
    tbody.innerHTML = filtered.length
        ? filtered.map(renderRow).join('')
        : '<tr><td colspan="5" class="empty-row">Žádné záznamy.</td></tr>';
}

function renderRow(r) {
    const isEnc = r.typ_operace === 'enc';
    const rowClass = isEnc ? 'enc-row' : 'dec-row';
    const opLabel = isEnc ? '🔒 enc' : '└ 🔓 dec';
    const btn = isEnc
        ? `<button class="btn-decrypt" onclick="decrypt(${r.id})">🔓 Dešifrovat</button>`
        : '';
    return `<tr class="${rowClass}">
        <td>${opLabel}</td>
        <td>${truncate(r.input, 45)}</td>
        <td class="${r.parent_id ? 'plaintext-result' : ''}">${truncate(r.output, 45)}</td>
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
                operation: CIPHER_TYPE + '_dec',
                input: r.output,
                cipher_key: r.cipher_key,
                parent_id: r.id
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
            body: JSON.stringify({ operation: CIPHER_TYPE + '_enc', input: text })
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

// PHP already rendered the table on page load.
// Populate data structures so filters and decrypt() work immediately.
document.addEventListener('DOMContentLoaded', () => {
    allRecords = (typeof INITIAL_RECORDS !== 'undefined') ? INITIAL_RECORDS : [];
    allRecords.forEach(r => { historyData[r.id] = r; });
});
