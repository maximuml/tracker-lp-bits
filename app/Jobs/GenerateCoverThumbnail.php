<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Generate a local JPEG thumbnail for a remote torrent cover image.
 *
 * The download/resizing is done asynchronously so that rendering the
 * homepage "latest torrents" grid never blocks on a slow or unreachable
 * cover host. The URL is validated against private/internal addresses before
 * any network request is made.
 */
class GenerateCoverThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param string $sourceUrl   Remote cover URL (http/https).
     * @param string $absolutePath Destination filesystem path for the JPEG thumbnail.
     * @param int    $maxWidth
     * @param int    $maxHeight
     * @param int    $quality
     */
    public function __construct(
        public readonly string $sourceUrl,
        public readonly string $absolutePath,
        public readonly int $maxWidth = 240,
        public readonly int $maxHeight = 360,
        public readonly int $quality = 82,
    ) {
    }

    public function handle(): void
    {
        if (is_file($this->absolutePath) && filesize($this->absolutePath) > 0) {
            return;
        }

        if (! self::isAllowedUrl($this->sourceUrl)) {
            return;
        }

        $data = self::fetch($this->sourceUrl);
        if ($data === null || $data === '') {
            return;
        }

        $src = @imagecreatefromstring($data);
        if (! $src) {
            return;
        }

        $srcWidth = imagesx($src);
        $srcHeight = imagesy($src);

        $scale = min(1.0, $this->maxWidth / $srcWidth, $this->maxHeight / $srcHeight);
        $dstWidth = max(1, (int) floor($srcWidth * $scale));
        $dstHeight = max(1, (int) floor($srcHeight * $scale));

        $dst = imagecreatetruecolor($dstWidth, $dstHeight);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

        $dir = dirname($this->absolutePath);
        if (! is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        @imagejpeg($dst, $this->absolutePath, max(1, min(100, $this->quality)));
        imagedestroy($src);
        imagedestroy($dst);
    }

    /**
     * Reject URLs that point to non-HTTP schemes, loopback, link-local,
     * private ranges, or metadata services.
     *
     * Handles both plain IPv4 addresses and bracketed IPv6 literals
     * (e.g. `[::1]`, `[fc00::1]`, `[::ffff:169.254.169.254]`).
     */
    public static function isAllowedUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return false;
        }

        $scheme = strtolower($parsed['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $host = $parsed['host'] ?? '';
        if ($host === '') {
            return false;
        }

        // Strip brackets from IPv6 literals: `[::1]` -> `::1`.
        $host = trim($host, '[]');
        if ($host === '') {
            return false;
        }

        // Reject hostname-based localhost / internal names.
        $lowerHost = strtolower($host);
        $blockedNames = ['localhost', 'metadata.google.internal'];
        if (in_array($lowerHost, $blockedNames, true)) {
            return false;
        }

        // Literal IP address (IPv4 or IPv6): reject private/reserved/link-local.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        // For hostnames, resolve DNS and check all returned IPs.
        // This prevents trivial DNS-rebinding to internal addresses. A small TTL
        // window remains, but it closes the most common SSRF vector.
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records !== false) {
            $found = false;
            foreach ($records as $record) {
                $type = $record['type'] ?? '';
                if ($type === 'A') {
                    $ip = $record['ip'] ?? '';
                } elseif ($type === 'AAAA') {
                    $ip = $record['ipv6'] ?? '';
                } else {
                    continue;
                }

                if ($ip === '') {
                    continue;
                }

                $found = true;
                if (! self::isPublicIp($ip)) {
                    return false;
                }
            }

            if ($found) {
                return true;
            }
        }

        // Fallback for environments without dns_get_record support.
        $ipv4s = gethostbynamel($host);
        if ($ipv4s !== false) {
            foreach ($ipv4s as $ip) {
                if (! self::isPublicIp($ip)) {
                    return false;
                }
            }

            return true;
        }

        // No resolvable public records; block to avoid DNS-rebinding to internal addresses.
        return false;
    }

    /**
     * @param string $ip IPv4 or IPv6 address (must not be bracketed).
     */
    private static function isPublicIp(string $ip): bool
    {
        // filter_var flags do not always reject IPv4-mapped IPv6 addresses
        // (e.g. `::ffff:127.0.0.1`), so validate the embedded IPv4 explicitly.
        $lower = strtolower($ip);
        if (str_starts_with($lower, '::ffff:')) {
            $ipv4 = substr($ip, 7);
            if (filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE | FILTER_FLAG_IPV4) === false) {
                return false;
            }
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * Download the remote image with safe cURL options: no redirects,
     * limited protocols, SSL verification enabled, short timeouts, and
     * a hard cap on response body size.
     */
    private static function fetch(string $url): ?string
    {
        if (! function_exists('curl_init')) {
            $ctx = stream_context_create([
                'http' => ['timeout' => 5, 'follow_location' => 0],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);

            $data = @file_get_contents($url, false, $ctx);

            return $data !== false ? $data : null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $buffer = '';
        $maxBytes = 5 * 1024 * 1024; // 5 MB

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'NexusPHP/cover-thumb');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($_, $chunk) use (&$buffer, $maxBytes): int {
            $buffer .= $chunk;
            if (strlen($buffer) > $maxBytes) {
                return 0; // abort oversized download
            }

            return strlen($chunk);
        });

        curl_exec($ch);
        $error = curl_errno($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error === CURLE_ABORTED_BY_CALLBACK) {
            return null;
        }
        if ($error !== 0) {
            return null;
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            return null;
        }

        return $buffer;
    }
}
