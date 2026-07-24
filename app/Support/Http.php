<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Stateless HTTP-header / HTTP-URL helpers extracted from
 * `include/functions.php` and `include/globalfunctions.php`
 * (Phase 5 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 5 — drain `include/functions.php`").
 *
 * Legacy procedural helpers backed by this class:
 *
 *   - `make_content_disposition($filename, $disposition)` →
 *     {@see Http::contentDisposition()}
 *   - `get_protocol_prefix()` → {@see Http::protocolPrefix()}
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Token}, {@see Strings}, {@see Network}.
 *
 * Every method's contract is pinned by a unit test in
 * `tests/Unit/Support/HttpTest.php`.
 */
final class Http
{
    /**
     * Build a `Content-Disposition` header value with the given
     * `$filename` and `$disposition` (`'attachment'` or `'inline'`).
     *
     * The ASCII fallback name is derived from `Str::ascii($filename)`
     * with `%` stripped — matching the legacy contract exactly so
     * UTF-8 torrent filenames keep working across the legacy HTTP
     * download path.
     *
     * Internally delegates to Symfony's
     * `HeaderUtils::makeDisposition()` which is the same call the
     * legacy helper uses; this class exists so the dependency lives
     * in `App\Support` and not in `include/functions.php`.
     */
    public static function contentDisposition(
        string $filename,
        string $disposition = 'attachment',
    ): string {
        $filenameFallback = str_replace('%', '', Str::ascii($filename));

        return HeaderUtils::makeDisposition($disposition, $filename, $filenameFallback);
    }

    /**
     * Return `"https://"` when `$isHttps` is true, `"http://"` otherwise.
     *
     * Backs the legacy `get_protocol_prefix()` global, which determined
     * "is HTTPS" by calling `isHttps()` (in `include/globalfunctions.php`).
     * The typed method takes the bool directly so the secret-sauce
     * detection (`nexus()->getRequestSchema()` + the
     * `security.securelogin` console fallback) stays at the caller —
     * the legacy proxy in `include/functions.php` keeps wiring the
     * two together.
     */
    public static function protocolPrefix(bool $isHttps): string
    {
        return $isHttps ? 'https://' : 'http://';
    }
}
