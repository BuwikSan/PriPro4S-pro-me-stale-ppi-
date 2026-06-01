"""
ML-KEM (Module-Lattice-Based Key-Encapsulation Mechanism)
NIST FIPS 203, August 2024 — from-scratch implementation.
Dependencies: only Python stdlib (hashlib, os).
"""

import hashlib
import os

# ============================================================
# PARAMETERS — ML-KEM-512 (FIPS 203 Table 2)
# ============================================================
N    = 256   # polynomial degree
Q    = 3329  # prime modulus: 13·2^8 + 1
K    = 2     # module rank
ETA1 = 3     # CBD noise parameter for s, e
ETA2 = 2     # CBD noise parameter for e1, e2
DU   = 10    # bits/coeff in u compression
DV   = 4     # bits/coeff in v compression

EK_SIZE   = 384 * K + 32   # encapsulation key: 800 bytes
DK_PKE_SZ = 384 * K        # PKE secret key:    768 bytes


# ============================================================
# HASH / XOF FUNCTIONS (FIPS 203 §4.1)
# ============================================================

###################################### Domain separation pomocí veřejného klíče ######################################
def _G(b):
    """G = SHA3-512, rozděl na dva 32bytové seedy.

    Používá se na domain separation (hashování s H(vk)) a odvozování klíčů.
    """
    h = hashlib.sha3_512(b).digest()  # SHA3-512 → 64 bytů
    return h[:32], h[32:]             # vrátí (left 32B, right 32B)

def _H(b):
    """H = SHA3-256, vrátí 32 bytů.

    Používá se na hashování veřejného klíče.
    """
    return hashlib.sha3_256(b).digest()

def _XOF(rho, i, j):
    """XOF = SHAKE-128 seeded with rho||i||j, vrátí 840 bytů.

    Extendable Output Function - používá se na vzorkování matice A
    (pro každý prvek A[i][j] dostaneš 840 bytů pro rejection sampling).
    """
    return hashlib.shake_128(rho + bytes([i, j])).digest(840)

def _PRF(eta, s, b):
    """PRF = SHAKE-256 seeded with s||b, vrátí 64·eta bytů.

    Pseudorandom Function - používá se na vzorkování šumů.
    """
    return hashlib.shake_256(s + bytes([b])).digest(64 * eta)


###################################### Fujisaki-Okamoto _J() ######################################
def _J(z, c):
    """J = SHAKE-256 implicit-rejection KDF.

    Když decaps selže, vrátíš J(z, c) místo signalizovat chybu.
    z je privátní → útočník nemůže vyrábět J(z, c).
    """
    return hashlib.shake_256(z + c).digest(32)

# ============================================================
# NTT PRECOMPUTATION
# ============================================================
def _bit_rev7(x):
    """Reverse the 7 lowest-order bits of x."""
    r = 0
    for _ in range(7):
        r = (r << 1) | (x & 1)
        x >>= 1
    return r

# Twiddle factors for NTT butterflies: ZETAS[i] = 17^BitRev7(i) mod Q
ZETAS  = [pow(17, _bit_rev7(i), Q) for i in range(128)]
# Factors for base-case multiplication: GAMMAS[i] = 17^(2·BitRev7(i)+1) mod Q
GAMMAS = [pow(17, 2 * _bit_rev7(i) + 1, Q) for i in range(128)]

# ============================================================
# NTT  (FIPS 203 Algorithms 9–11)
# ============================================================
def ntt(f):
    """Forward NTT: list[256] → NTT-domain list[256].

    Transformuje polynom z koeficientní reprezentace na NTT reprezentaci.
    """
    f = list(f)  # kopie polynomu (256 koeficientů)
    k = 1        # index do ZETAS (twiddle faktory)
    length = 128 # šířka bloku v prvním průchodu (N/2)

    while length >= 2:  # 7 vrstev (length = 128, 64, 32, ..., 2)
        for start in range(0, N, 2 * length):  # procházej bloky
            z = ZETAS[k]      # twiddle faktor pro tuto vrstvu
            k += 1
            for j in range(start, start + length):  # butterfly operace
                t             = z * f[j + length] % Q  # (f[j+len] * z)
                f[j + length] = (f[j] - t) % Q         # dolní výstup
                f[j]          = (f[j] + t) % Q         # horní výstup
        length //= 2  # smaž na polovinu
    return f

def intt(f):
    """Inverse NTT: NTT-domain list[256] → polynomial list[256].

    Obrátí NTT transformaci - vrátí původní koeficienty.
    """
    f = list(f)    # kopie NTT reprezentace
    k = 127        # index do ZETAS (počítáme od konce)
    length = 2     # šířka bloku (N/128 v prvním průchodu)

    while length <= 128:  # 7 vrstev (length = 2, 4, 8, ..., 128)
        for start in range(0, N, 2 * length):
            z = ZETAS[k]   # twiddle faktor
            k -= 1         # počítáme dolů
            for j in range(start, start + length):  # reverse butterfly
                t             = f[j]
                f[j]          = (t + f[j + length]) % Q
                f[j + length] = z * (f[j + length] - t) % Q
        length *= 2  # zdvojnásob šířku

    # Normalizace: vynásob všechny koeficienty 128^(-1) mod Q
    inv = pow(128, Q - 2, Q)  # inverzní prvek pomocí Fermatova teorému
    return [x * inv % Q for x in f]

def _mul_ntt(f, g):
    """Multiply two NTT-domain polynomials (base-case for quadratic factors).

    V NTT doméně se násobení redukuje na base-case násobení v malých
    kvadratických kroužích Z_Q[X]/(X² - γ_i).
    """
    h = [0] * N  # výstup (součin f * g)

    for i in range(128):  # 128 kvadratických faktorů
        # Vezmi i-tý pair z f a g
        a0, a1 = f[2*i], f[2*i+1]       # f v i-tém faktoru: a0 + a1·X
        b0, b1 = g[2*i], g[2*i+1]       # g v i-tém faktoru: b0 + b1·X
        gm = GAMMAS[i]                   # γ_i pro tento faktor

        # Násobení (a0 + a1·X)(b0 + b1·X) mod (X² - γ_i):
        # = a0·b0 + a1·b1·γ_i  +  (a0·b1 + a1·b0)·X
        h[2*i]   = (a0*b0 + a1*b1*gm) % Q  # koeficient u X⁰
        h[2*i+1] = (a0*b1 + a1*b0)    % Q  # koeficient u X¹

    return h

def _add(a, b): return [(x+y) % Q for x, y in zip(a, b)]
def _sub(a, b): return [(x-y) % Q for x, y in zip(a, b)]








# ============================================================
# SAMPLING  (Algorithms 7–8)
# ============================================================
def _sample_ntt(seed):
    """SampleNTT: rejection-sample 256 koeficientů v [0,Q) z XOF bytů.

    Vezmeš 3 byty najednou, vytvoříš 2 kandidáty na 12 bitů,
    a přijmeš jen ty, které jsou < Q (rejection sampling).
    """
    a = []  # seznam přijatých koeficientů (bude mít 256)
    i = 0   # index do seed bytů

    while len(a) < N:  # dokud nemáš 256 koeficientů
        # Rozšiř seed pokud běž na konec
        if i + 3 > len(seed):
            seed = hashlib.shake_128(seed).digest(len(seed) + 168)

        # Vezmi 3 byty a vytvoř 2 kandidáty (12-bitové čísla)
        d1 = seed[i] + 256 * (seed[i+1] % 16)      # byte0 + dolní 4 bity byte1
        d2 = (seed[i+1] // 16) + 16 * seed[i+2]    # horní 4 bity byte1 + byte2
        i += 3

        # Rejection: přijmi jen pokud < Q (zajistí uniformitu)
        if d1 < Q:                a.append(d1)
        if d2 < Q and len(a) < N: a.append(d2)

    return a

def _sample_cbd(eta, prf_bytes):
    """SamplePolyCBD_eta: Centered Binomial Distribution (malý šum).

    Vezmeš 64*eta bytů, rozpakuješ je na bity, a pro každý koef.
    vytvoříš rozdíl dvou binomických součtů.
    """
    f = [0] * N    # výstupní polynom (256 koeficientů)
    bits = []      # rozpakované bity (512*eta bitů)

    # Rozpakuj byty na jednotlivé bity (little-endian)
    for byte in prf_bytes[:64 * eta]:
        for b in range(8):
            bits.append((byte >> b) & 1)

    # Pro každý koef.: vezmi η bitů, vezmi dalších η bitů, jejich rozdíl
    for i in range(N):
        # Suma prvních η bitů (pro tento koef)
        a_s = sum(bits[2*i*eta : 2*i*eta + eta])
        # Suma druhých η bitů
        b_s = sum(bits[2*i*eta + eta : 2*i*eta + 2*eta])
        # Koef = rozdíl (bude v rozsahu [-η, η])
        f[i] = (a_s - b_s) % Q

    return f

# ============================================================
# ENCODE / DECODE / COMPRESS / DECOMPRESS  (Algorithms 4–6)
# ============================================================


def _byte_encode(f, d):
    """ByteEncode_d: zabal N d-bitových koef. do 32d bytů.

    Vezmi 256 koef., každý na d bitů, a zabal je do bytů.
    Např. d=12: 256*12 = 3072 bitů = 384 bytů = 32*12.
    """
    out = bytearray(32 * d)  # výstupní byty

    for i in range(N):  # pro každý koef (0..255)
        c = int(f[i]) & ((1 << d) - 1)  # vezmi spodních d bitů
        for j in range(d):  # pro každý bit v koef
            p = i * d + j  # globální pozice bitu
            out[p >> 3] |= ((c >> j) & 1) << (p & 7)  # nastav bit

    return bytes(out)

def _byte_decode(b, d):
    """ByteDecode_d: rozpakuj 32d bytů do 256 d-bitových koef.

    Obrácená operace k _byte_encode.
    """
    f = [0] * N  # výstupní koeficienty

    for i in range(N):  # pro každý koef
        for j in range(d):  # pro každý bit v koef
            p = i * d + j  # globální pozice bitu
            f[i] |= ((b[p >> 3] >> (p & 7)) & 1) << j  # vezmi bit a nastav

    return f

def _compress(f, d):
    """Compress_q: zmenši koef. z [0,Q) na [0,2^d) (zmenšení přesnosti).

    Například d=10: koef. se změní z 12 bitů na 10 bitů.
    Chyba ≈ Q/2^(d+1), což je přijatelné.
    """
    m = 1 << d  # 2^d
    return [round(x * m / Q) % m for x in f]

def _decompress(f, d):
    """Decompress_q: zvětši koef. z [0,2^d) zpět na [0,Q).

    Obrácená operace k compress - vrátíš se přibližně zpátky.
    """
    return [round(x * Q / (1 << d)) % Q for x in f]

# ============================================================
# K-PKE — IND-CPA secure encryption  (Algorithms 13–15)
# ============================================================
def _pke_keygen(keygen_seed):
    """K-PKE KeyGen: vytvoř veřejný klíč (t, A) a privátní klíč (s).

    Args:
        d: 32bytový seed
    Returns:
        ek_pke: veřejný klíč (800 bytů) = t[0]||t[1]||ρ
        pk_pke: privátní klíč (768 bytů) = s[0]||s[1] (v NTT doméně)
    """
    # Domain separation: hash d s K (module rank) pro generování seedů
    rho, sigma = _G(keygen_seed + bytes([K]))
    # ρ = seed pro veřejnou matici A
    # σ = seed pro šum (s, e)

    # Vygeneruj veřejnou matici A v NTT doméně (K×K polynomů)
    A = [[_sample_ntt(_XOF(rho, i, j)) for j in range(K)] for i in range(K)]

    # Vygeneruj privátní vektor s a chybový vektor e (oba K polynomů)
    # CBD vzorkování, pak transformace do NTT domény
    s_hat = [ntt(_sample_cbd(ETA1, _PRF(ETA1, sigma, i)))     for i in range(K)]
    e_hat = [ntt(_sample_cbd(ETA1, _PRF(ETA1, sigma, K + i))) for i in range(K)]

    # Vypočítej veřejný vektor t = A·s + e (v NTT doméně)
    p_hat = []
    for i in range(K):
        ti = [0] * N  # i-tý řádek výsledku
        for j in range(K):
            ti = _add(ti, _mul_ntt(A[i][j], s_hat[j]))  # sum_j A[i][j] * s[j]
        p_hat.append(_add(ti, e_hat[i]))  # přidej chybu

    # Kóduj a vrátí klíče
    vk = b''.join(_byte_encode(p_hat[i], 12) for i in range(K)) + rho
    pk = b''.join(_byte_encode(s_hat[i], 12) for i in range(K))
    return vk, pk

def _pke_encrypt(vk, m, r):
    p_hat = [_byte_decode(vk[384*i : 384*(i+1)], 12) for i in range(K)]
    rho   = vk[384*K:]
    A     = [[_sample_ntt(_XOF(rho, i, j)) for j in range(K)] for i in range(K)]

    r_hat = [ntt(_sample_cbd(ETA1, _PRF(ETA1, r, i)))     for i in range(K)]
    e1    = [_sample_cbd(ETA2, _PRF(ETA2, r, K + i))      for i in range(K)]
    e2    = _sample_cbd(ETA2, _PRF(ETA2, r, 2 * K))
    mu    = _decompress(_byte_decode(m, 1), 1)

    # u = A^T · r + e1
    u = []
    for j in range(K):
        uj = [0] * N
        for i in range(K):
            uj = _add(uj, _mul_ntt(A[i][j], r_hat[i]))
        u.append(_add(intt(uj), e1[j]))

    # v = t^T · r + e2 + mu
    v_hat = [0] * N
    for i in range(K):
        v_hat = _add(v_hat, _mul_ntt(p_hat[i], r_hat[i]))
    v = _add(_add(intt(v_hat), e2), mu)

    # Kompresuj a kóduj ciphertext (zmenší velikost na DU=10 a DV=4 bitů)
    c1 = b''.join(_byte_encode(_compress(u[i], DU), DU) for i in range(K))
    c2 = _byte_encode(_compress(v, DV), DV)
    return c1 + c2  # vrátí 640+128=768 bytů

def _pke_decrypt(pk, c):
    """K-PKE Decrypt: dešifruj ciphertext c tajným klíčem pk.

    Args:
        pk: privátní klíč (768 bytů) = s[0]||s[1] v NTT doméně
        c: ciphertext (768 bytů) = c1||c2
    Returns:
        Zpráva m (32 bytů)
    """
    # Dekóduj ciphertext (decompressuj z menších velikostí)
    u     = [_decompress(_byte_decode(c[32*DU*i : 32*DU*(i+1)], DU), DU) for i in range(K)]
    v     = _decompress(_byte_decode(c[32*DU*K:], DV), DV)
    # Dekóduj privátní klíč
    s_hat = [_byte_decode(pk[384*i : 384*(i+1)], 12) for i in range(K)]

    # Vypočítej w = v - s^T · u (malý šum se zruší, zbude přibližně m)
    w = list(v)
    for i in range(K):
        w = _sub(w, intt(_mul_ntt(s_hat[i], ntt(u[i]))))  # sum_i s[i] * u[i]
    return _byte_encode(_compress(w, 1), 1)  # kompresuj zpátky na 1 bit

# ============================================================
# ML-KEM — IND-CCA2 KEM via Fujisaki-Okamoto transform
#           (Algorithms 19–21)
# ============================================================
def keygen():
    """ML-KEM.KeyGen() → (vk, pk) - Vytvoření páru klíčů.

    Returns:
        vk: encapsulation key (800 bytes) - veřejný klíč
        pk: decapsulation key (1632 bytes) - privátní klíč
            Struktura: pk_pke (768B) || vk (800B) || H(vk) (32B) || ir_seed (32B)
    """
    keygen_seed = os.urandom(32)   # random seed pro keygen
    ir_seed = os.urandom(32)   # random seed pro implicit rejection (při selhání decaps)

    ek_pke, pk_pke = _pke_keygen(keygen_seed)  # vytvoř PKE klíče

    vk = ek_pke  # veřejný klíč
    pk = pk_pke + vk + _H(vk) + ir_seed  # privátní klíč (4 části)

    return vk, pk

def encaps(vk):
    """ML-KEM.Encaps(vk) → (K, c) - Encapsulace sdíleného tajemství.

    Generuješ náhodné číslo m, šifruješ ho, a vrátíš klíč K a ciphertext c.

    Returns:
        K: shared secret (32 bytes) - sdílený klíč
        c: ciphertext (768 bytes) - zašifrované m pod veřejným klíčem vk
    """
    m = os.urandom(32)           # náhodná 32bytová zpráva
    # Domain separation pomocí veřejného klíče pomocí _G()
    K_key, r = _G(m + _H(vk))   # hashovej m+H(vk) a rozdělí na K a r
                                 # K = sdílený klíč
                                 # r = randomness pro šifrování
    c = _pke_encrypt(vk, m, r)   # šifruj m pod vk s randomness r

    return K_key, c

def decaps(pk, c):
    """ML-KEM.Decaps(pk, c) → K - Decapsulace sdíleného tajemství.

    Dešifruješ zprávu m z ciphertextu c, znovuencryptuješ ji, a zkontroluj
    Fujisaki-Okamoto: pokud se ciphertext shoduje, vrátíš správný klíč K'.
    Pokud ne (útok/poškozená data), vrátíš fake klíč J(ir_seed, c).

    Returns:
        K: shared secret (32 bytes) - buď správný nebo pseudonáhodný (bez signálu)
    """
    # Rozparsuj pk na 4 komponenty
    pk_pke = pk[:DK_PKE_SZ]                           # PKE privátní klíč (768B)
    vk     = pk[DK_PKE_SZ : DK_PKE_SZ + EK_SIZE]     # veřejný klíč (800B)
    h      = pk[DK_PKE_SZ + EK_SIZE : DK_PKE_SZ + EK_SIZE + 32]  # H(vk) (32B)
    ir_seed = pk[DK_PKE_SZ + EK_SIZE + 32:]           # implicit rejection seed (32B)

    # Dešifruj zprávu
    m_prime = _pke_decrypt(pk_pke, c)                # vezmi tajné s, vrátí m'

    # Znovuencrypt: "simuluj" encaps s m_prime
    K_prime, r_p = _G(m_prime + h)                   # spočítej K' a r'
    c_prime = _pke_encrypt(vk, m_prime, r_p)         # re-encrypt s m' a r'


    # Fujisaki-Okamoto: přesnost ověří
    if c == c_prime:
        # ✓ Ciphertext je správný → vrátí správný klíč
        return K_prime
    else:
        # ✗ Ciphertext je zmanipulovaný → vrátí fake (seedem z) (implicit rejection)
        # Útočník nemůže rozlišit!
        return _J(ir_seed, c)

# ============================================================
# TEXT ENCRYPTION HELPERS  (KEM + SHAKE-256 stream cipher)
# ============================================================
def encrypt_text(vk, plaintext: str) -> tuple:
    """Hybrid encrypt: ML-KEM + SHAKE-256 stream cipher.

    Encapsuluješ sdílený klíč K, generuješ keystream z K, XORuješ text.

    Args:
        vk: veřejný ML-KEM klíč
        plaintext: text k šifrování

    Returns:
        (c_kem, ct): KEM ciphertext (768B) + encrypted text (variable length)
    """
    K_key, c_kem = encaps(vk)  # vygeneruj K a ciphertext

    # Převeď text na UTF-8 byty
    pt = plaintext.encode('utf-8')

    # Generuj keystream z K pomocí SHAKE-256 (stejné délky jako text)
    ks = hashlib.shake_256(K_key).digest(len(pt))

    # XOR plaintext s keystreamem
    ct = bytes(a ^ b for a, b in zip(pt, ks))

    return c_kem, ct

def decrypt_text(pk, c_kem: bytes, ct: bytes) -> str:
    """Hybrid decrypt: ML-KEM + SHAKE-256 stream cipher.

    Decapsuluješ K z c_kem, generuješ keystream z K, XORuješ ciphertext.

    Args:
        pk: privátní ML-KEM klíč
        c_kem: KEM ciphertext (768 bytes)
        ct: encrypted text (variable length)

    Returns:
        Dešifrovaný text, nebo hex-dump garbage pokud selže decryption
    """
    K_key = decaps(pk, c_kem)  # obnovíš K (buď správný nebo fake)

    # Generuj stejný keystream
    ks = hashlib.shake_256(K_key).digest(len(ct))

    # XOR ciphertext s keystreamem
    pt = bytes(a ^ b for a, b in zip(ct, ks))

    try:
        return pt.decode('utf-8')
    except UnicodeDecodeError:
        # Pokud was K fake (špatný klíč), text bude garbage
        return f'[DECRYPTION FAILED — garbage bytes]: {pt[:40].hex()}...'
