/**
 * SHA-256 / HMAC-SHA256 for the challenge-response login — replaces
 * crypto-js.js (216 KB). crypto.subtle is used on secure contexts
 * (HTTPS + localhost/127.0.0.1); a compact pure-JS fallback covers
 * plain-http installs where subtle is undefined.
 *
 * API matches the old call sites: nxCrypto.sha256(str) and
 * nxCrypto.hmacSha256(message, key) are async and return lowercase hex.
 */
window.nxCrypto = (function () {
    var encoder = new TextEncoder();

    function hex(buf) {
        return Array.from(new Uint8Array(buf), function (b) {
            return b.toString(16).padStart(2, '0');
        }).join('');
    }

    /* --- compact SHA-256 (FIPS 180-4), fallback path only --- */
    var K = [
        0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
        0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
        0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
        0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
        0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
        0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
        0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
        0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
    ];

    function rr(x, n) { return (x >>> n) | (x << (32 - n)); }

    function sha256Sync(bytes) {
        var bitLenHi = Math.floor(bytes.length / 0x20000000);
        var bitLenLo = (bytes.length * 8) >>> 0;
        var len = bytes.length + 9 + ((64 - ((bytes.length + 9) % 64)) % 64);
        var m = new Uint8Array(len);
        m.set(bytes);
        m[bytes.length] = 0x80;
        var dv = new DataView(m.buffer);
        dv.setUint32(len - 8, bitLenHi);
        dv.setUint32(len - 4, bitLenLo);
        var h = [0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19];
        var w = new Uint32Array(64);
        for (var off = 0; off < len; off += 64) {
            for (var i = 0; i < 16; i++) { w[i] = dv.getUint32(off + i * 4); }
            for (i = 16; i < 64; i++) {
                var s0 = rr(w[i - 15], 7) ^ rr(w[i - 15], 18) ^ (w[i - 15] >>> 3);
                var s1 = rr(w[i - 2], 17) ^ rr(w[i - 2], 19) ^ (w[i - 2] >>> 10);
                w[i] = (w[i - 16] + s0 + w[i - 7] + s1) >>> 0;
            }
            var a = h[0], b = h[1], c = h[2], d = h[3], e = h[4], f = h[5], g = h[6], hh = h[7];
            for (i = 0; i < 64; i++) {
                var S1 = rr(e, 6) ^ rr(e, 11) ^ rr(e, 25);
                var ch = (e & f) ^ (~e & g);
                var t1 = (hh + S1 + ch + K[i] + w[i]) >>> 0;
                var S0 = rr(a, 2) ^ rr(a, 13) ^ rr(a, 22);
                var maj = (a & b) ^ (a & c) ^ (b & c);
                var t2 = (S0 + maj) >>> 0;
                hh = g; g = f; f = e; e = (d + t1) >>> 0; d = c; c = b; b = a; a = (t1 + t2) >>> 0;
            }
            h[0] = (h[0] + a) >>> 0; h[1] = (h[1] + b) >>> 0; h[2] = (h[2] + c) >>> 0; h[3] = (h[3] + d) >>> 0;
            h[4] = (h[4] + e) >>> 0; h[5] = (h[5] + f) >>> 0; h[6] = (h[6] + g) >>> 0; h[7] = (h[7] + hh) >>> 0;
        }
        var out = new Uint8Array(32);
        var odv = new DataView(out.buffer);
        for (i = 0; i < 8; i++) { odv.setUint32(i * 4, h[i]); }
        return out;
    }

    function concat(a, b) {
        var r = new Uint8Array(a.length + b.length);
        r.set(a); r.set(b, a.length);
        return r;
    }

    function hmacSync(keyBytes, msgBytes) {
        if (keyBytes.length > 64) { keyBytes = sha256Sync(keyBytes); }
        var pad = new Uint8Array(64);
        pad.set(keyBytes.subarray(0, 64));
        var ipad = new Uint8Array(64), opad = new Uint8Array(64);
        for (var i = 0; i < 64; i++) {
            ipad[i] = pad[i] ^ 0x36;
            opad[i] = pad[i] ^ 0x5c;
        }
        return sha256Sync(concat(opad, sha256Sync(concat(ipad, msgBytes))));
    }

    return {
        sha256: async function (message) {
            var data = encoder.encode(message);
            if (crypto.subtle) {
                return hex(await crypto.subtle.digest('SHA-256', data));
            }
            return hex(sha256Sync(data));
        },
        hmacSha256: async function (message, key) {
            var keyBytes = encoder.encode(key);
            var msgBytes = encoder.encode(message);
            if (crypto.subtle) {
                var ck = await crypto.subtle.importKey(
                    'raw', keyBytes, { name: 'HMAC', hash: 'SHA-256' }, false, ['sign']
                );
                return hex(await crypto.subtle.sign('HMAC', ck, msgBytes));
            }
            return hex(hmacSync(keyBytes, msgBytes));
        }
    };
})();
