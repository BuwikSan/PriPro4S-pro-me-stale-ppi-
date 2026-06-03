#!/usr/bin/env python3
"""
Wrapper: čte JSON ze STDIN, vrátí JSON na STDOUT.
PHP volá: proc_open('python3 cipher_wrapper.py', ...)
"""
import sys, json, os, base64
import numpy as np

sys.path.insert(0, os.path.dirname(__file__))
from cypher_py_modules.hill_cypher import Hills_cypher
from cypher_py_modules import ml_kem


def _matrix_to_list(matrix):
    # Převeď numpy matici na list nativních Python int
    # (sympy inv_mod vrátí sympy.Integer, ne int — json.dumps() by spadl)
    return [[int(x) for x in row] for row in matrix.tolist()]

def handle_hill_enc(text):
    cipher = Hills_cypher()
    ciphertext = cipher.cypher(text)
    keys_data = {
        'keys':           [_matrix_to_list(k) for k in cipher.keys],
        'inverse_keys':   [_matrix_to_list(k) for k in cipher.inverse_keys],
        'padding_length': int(cipher.padding_length)
    }
    return {'success': True, 'ciphertext': ciphertext, 'keys_data': keys_data}


def handle_hill_dec(ciphertext, keys_data):
    cipher = Hills_cypher()          # instance s novými klíči (zahodíme je)
    # Přepíš uloženými klíči z DB
    cipher.keys         = [np.array(k) for k in keys_data['keys']]
    cipher.inverse_keys = [np.array(k) for k in keys_data['inverse_keys']]
    cipher.padding_length = keys_data.get('padding_length', 0)
    plaintext = cipher.decypher(ciphertext)
    return {'success': True, 'plaintext': plaintext}


def handle_mlkem_enc(text):
    vk, pk = ml_kem.keygen()
    c_kem, ct = ml_kem.encrypt_text(vk, text)
    return {
        'success': True,
        'ct':    base64.b64encode(ct).decode(),
        'pk':    base64.b64encode(pk).decode(),
        'c_kem': base64.b64encode(c_kem).decode()
    }


def handle_mlkem_dec(ct_b64, pk_b64, c_kem_b64):
    pk    = base64.b64decode(pk_b64)
    c_kem = base64.b64decode(c_kem_b64)
    ct    = base64.b64decode(ct_b64)
    plaintext = ml_kem.decrypt_text(pk, c_kem, ct)
    return {'success': True, 'plaintext': plaintext}


if __name__ == '__main__':
    try:
        req = json.load(sys.stdin)
        op  = req.get('operation', '')

        if op == 'hill_enc':
            resp = handle_hill_enc(req.get('text', ''))
        elif op == 'hill_dec':
            resp = handle_hill_dec(req.get('text', ''), req.get('keys_data', {}))
        elif op == 'mlkem_enc':
            resp = handle_mlkem_enc(req.get('text', ''))
        elif op == 'mlkem_dec':
            resp = handle_mlkem_dec(req.get('ct', ''), req.get('pk', ''), req.get('c_kem', ''))
        else:
            resp = {'success': False, 'error': f'Unknown operation: {op}'}

        print(json.dumps(resp))

    except Exception as e:
        print(json.dumps({'success': False, 'error': str(e)}))
