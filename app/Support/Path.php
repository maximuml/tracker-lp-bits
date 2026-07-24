<?php

namespace App\Support;

/**
 * Filesystem-path helpers extracted from `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * Backs the legacy `getFullDirectory($dir)` and `make_folder($pre, $name)`
 * which both consult the global `ROOT_PATH` constant. The typed API
 * takes `$rootPath` as an explicit parameter so the legacy proxies are
 * the only place that has to know about the constant — that makes the
 * helpers trivially unit-testable against a `sys_get_temp_dir()`-style
 * sandbox.
 *
 * Legacy quirks preserved bit-for-bit:
 *  - `resolve()` returns the input verbatim when it points at an
 *    existing FILE, prepends `$rootPath` when it's not already a
 *    directory, then `realpath()`s the result iff the prepended path
 *    is a directory. When `realpath()` would return `false` (only
 *    possible under race conditions, since we already checked
 *    `is_dir()`), the non-prepended path is returned instead — the
 *    legacy returned `false` here, which silently turned into `""`
 *    at every string-concat call site. The fallback to `$dir`
 *    avoids that footgun without surprising any current caller
 *    (none of them rely on `false`).
 *  - `makeFolder()` builds `$rootPath . ltrim($prefix . $name, './')`
 *    — note `ltrim` with the mask `./` strips ANY mix of `.` and `/`
 *    characters from the left, so `./cache/foo`, `/cache/foo`, and
 *    `.../cache/foo` all collapse to `cache/foo`. That's pinned by a
 *    test. The directory is created with mode `0777` recursively;
 *    the path is always returned even if `mkdir` failed (also legacy).
 *    `do_log()` from the original `make_folder` is kept in the proxy
 *    rather than the typed helper — logging is not a path concern.
 */
final class Path
{
    public static function resolve(string $dir, string $rootPath): string
    {
        if (is_file($dir) && file_exists($dir)) {
            return $dir;
        }
        if (! is_dir($dir)) {
            $dir = $rootPath.$dir;
        }
        if (is_dir($dir)) {
            $real = realpath($dir);
            if ($real !== false) {
                return $real;
            }
        }

        return $dir;
    }

    public static function makeFolder(string $prefix, string $folderName, string $rootPath): string
    {
        $path = $rootPath.ltrim($prefix.$folderName, './');
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }

        return $path;
    }

    /**
     * Build the relative icon folder for a category, extracted from the
     * legacy `get_cat_folder()`. The DB lookups (category row, icon row)
     * and the static per-request cache stay in the legacy proxy; only
     * the pure string assembly lives here:
     *
     *   category/{catMode}/{iconFolder}[/{langDir}]
     *
     * Each segment is `trim()`-ed of slashes (legacy `trim($_, '/')`),
     * so leading/trailing slashes on any input collapse and never
     * produce empty `//` segments. The language segment is appended
     * only when the icon set is flagged multilingual — matching the
     * legacy `$caticonrow['multilang'] == 'yes'` gate.
     */
    public static function categoryFolder(
        string $catMode,
        string $iconFolder,
        bool $multilang,
        string $langDir,
    ): string {
        $path = sprintf('category/%s/%s', trim($catMode, '/'), trim($iconFolder, '/'));
        if ($multilang) {
            $path .= '/'.trim($langDir, '/');
        }

        return $path;
    }
}
