import { createHash, randomBytes } from 'node:crypto';

/**
 * Minimal bencode helpers for the user-journey spec: build a unique
 * single-file .torrent, and read back what the tracker rewrote into
 * the downloaded copy (announce URL with passkey, re-hashed info dict).
 */
function enc(v: unknown): Buffer {
  if (typeof v === 'number') return Buffer.from(`i${v}e`);
  if (Buffer.isBuffer(v)) return Buffer.concat([Buffer.from(`${v.length}:`), v]);
  if (typeof v === 'string') return enc(Buffer.from(v));
  if (Array.isArray(v)) return Buffer.concat([Buffer.from('l'), ...v.map(enc), Buffer.from('e')]);
  const obj = v as Record<string, unknown>;
  const keys = Object.keys(obj).sort();
  return Buffer.concat([
    Buffer.from('d'),
    ...keys.flatMap((k) => [enc(k), enc(obj[k])]),
    Buffer.from('e'),
  ]);
}

export function makeTorrent(name: string, announce: string): Buffer {
  const pieceLength = 16384;
  const payload = randomBytes(pieceLength);
  return enc({
    announce,
    'created by': 'browser-e2e',
    info: {
      length: payload.length,
      name: `${name}.bin`,
      'piece length': pieceLength,
      pieces: createHash('sha1').update(payload).digest(),
    },
  });
}

/** Returns the end offset of the bencoded value starting at `i`. */
function skip(buf: Buffer, i: number): number {
  const c = String.fromCharCode(buf[i]);
  if (c === 'i') return buf.indexOf('e', i) + 1;
  if (c === 'l' || c === 'd') {
    let j = i + 1;
    while (buf[j] !== 0x65) j = skip(buf, j);
    return j + 1;
  }
  const colon = buf.indexOf(':', i);
  return colon + 1 + Number(buf.subarray(i, colon).toString());
}

export interface DownloadedTorrent {
  announce: string;
  infoHash: Buffer;
}

/** Top-level `announce` string and SHA-1 of the raw `info` dict bytes. */
export function readTorrent(buf: Buffer): DownloadedTorrent {
  if (buf[0] !== 0x64) throw new Error('not a bencoded dictionary');
  let i = 1;
  let announce = '';
  let infoHash: Buffer | null = null;
  while (buf[i] !== 0x65) {
    const keyEnd = skip(buf, i);
    const key = buf.subarray(buf.indexOf(':', i) + 1, keyEnd).toString();
    const valEnd = skip(buf, keyEnd);
    if (key === 'announce') {
      announce = buf.subarray(buf.indexOf(':', keyEnd) + 1, valEnd).toString();
    } else if (key === 'info') {
      infoHash = createHash('sha1').update(buf.subarray(keyEnd, valEnd)).digest();
    }
    i = valEnd;
  }
  if (!announce || !infoHash) throw new Error('torrent lacks announce or info');
  return { announce, infoHash };
}

/** Raw bytes → %XX for every byte, as BitTorrent clients send info_hash. */
export function percentEncode(bytes: Buffer): string {
  return [...bytes].map((b) => `%${b.toString(16).padStart(2, '0')}`).join('');
}
