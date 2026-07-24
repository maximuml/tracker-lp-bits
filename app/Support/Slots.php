<?php

namespace App\Support;

/**
 * Stateless helper for the legacy download-slot ("max slots") tier.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The procedural `maxslots()` helper mixed three concerns: the pure
 * tier calculation, a `get_user_class() < UC_VIP` gate, and the HTML
 * that gets echoed into the page. Only the first is portable, so it
 * collapses into the single static method below; `maxslots()` now
 * proxies here for the number and keeps its own HTML/permission shell.
 *
 * Lives under `App\Support` (not `App\Services`) because the method is
 * pure — no DI, no DB, no config, no global state. Same convention as
 * {@see Ratio} and {@see Imdb}.
 */
final class Slots
{
    /**
     * One gibibyte, matching the legacy `1024*1024*1024` divisor used
     * to turn the raw `uploaded` byte counter into "gigs".
     */
    private const GIB = 1024 * 1024 * 1024;

    /**
     * Resolve the simultaneous-download slot tier from a user's
     * uploaded/downloaded byte counters.
     *
     * Mirrors the legacy `maxslots()` ladder exactly:
     *
     *   - `ratio` is `uploaded / downloaded`, falling back to `1` when
     *     the user has never downloaded (matching the legacy
     *     `$downloaded > 0 ? ... : 1` guard, so a brand-new account is
     *     treated as ratio 1, not infinity or division-by-zero),
     *   - `gigs` is `uploaded / 1024³`,
     *   - the tiers are evaluated top-down with `||`, so the *lower* of
     *     the two qualifying conditions wins (a stellar ratio still
     *     caps at tier 1 until enough has been uploaded, and vice
     *     versa).
     *
     * Returns the slot count `1..4`, or `0` for "unlimited" — the
     * legacy sentinel the caller renders as the `text_unlimited`
     * label. The `||` boundaries are strict `<`, so a value sitting
     * exactly on a threshold falls through to the next tier (e.g. a
     * ratio of exactly `0.5` with ≥ 5 GiB skips tier 1).
     */
    public static function maxDownloadSlots(int|float $uploaded, int|float $downloaded): int
    {
        $gigs = $uploaded / self::GIB;
        $ratio = $downloaded > 0 ? $uploaded / $downloaded : 1;

        if ($ratio < 0.5 || $gigs < 5) {
            return 1;
        }
        if ($ratio < 0.65 || $gigs < 6.5) {
            return 2;
        }
        if ($ratio < 0.8 || $gigs < 8) {
            return 3;
        }
        if ($ratio < 0.95 || $gigs < 9.5) {
            return 4;
        }

        return 0;
    }
}
