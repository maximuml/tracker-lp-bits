<?php

namespace App\Support;

/**
 * Stateless IPv4 arithmetic helpers extracted from
 * `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The legacy procedural helper
 *
 *   - `in_ip_range($long, $targetip, $ip_one, $ip_two = false)`
 *
 * collapses into the static method below. The legacy function now
 * proxies here.
 *
 * Lives under `App\Support` (not `App\Services`) because every method
 * is pure — no DI, no DB, no config, no global state. Same convention
 * as {@see Ratio}, {@see Validators}, {@see Strings}.
 *
 * Every method's contract is pinned by a unit test in
 * `tests/Unit/Support/NetworkTest.php`.
 */
final class Network
{
    /**
     * Test whether an IPv4 address falls inside an IP range.
     *
     * Two calling conventions:
     *
     *   1. Single-IP check: `ipInRange($long, $targetIp, $ipOne)` —
     *      true iff `$targetIp` equals `$ipOne` (the legacy callers
     *      sometimes pass a one-element \"range\" rather than special-
     *      casing the lookup).
     *   2. Two-IP range: `ipInRange($long, $targetIp, $ipOne, $ipTwo)`
     *      — true iff `$ipOne <= $targetIp <= $ipTwo` (inclusive).
     *
     * `$long === true` means the bounds (`$ipOne` / `$ipTwo`) are
     * already integers from `ip2long()`, while `$targetIp` is still
     * a dotted-quad string. `$long === false` means everything is
     * a dotted-quad string and we run `ip2long()` on both sides.
     *
     * Matches the legacy `in_ip_range()` body exactly, including the
     * mixed-type contract (the legacy code calls this from
     * `get_ip_location()` where the `iplocation` table sometimes
     * stores ranges as integers and sometimes as dotted strings).
     */
    public static function ipInRange(
        bool $long,
        string $targetIp,
        mixed $ipOne,
        mixed $ipTwo = false,
    ): bool {
        if ($ipTwo === false) {
            return $long
                ? long2ip((int) $ipOne) === $targetIp
                : (string) $ipOne === $targetIp;
        }

        $targetLong = ip2long($targetIp);

        return $long
            ? ((int) $ipOne <= $targetLong && (int) $ipTwo >= $targetLong)
            : (ip2long((string) $ipOne) <= $targetLong
                && ip2long((string) $ipTwo) >= $targetLong);
    }

    /**
     * Legacy IPv4 format check used by the signup / invite flows.
     *
     * Returns `1` for a valid dotted-quad IPv4, `0` or `false` otherwise,
     * matching the legacy `preg_match()` return value.
     */
    public static function isValidIpv4Format(string $ip): int|false
    {
        $pattern = '/\b(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.'
            . '(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.'
            . '(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.'
            . '(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/';

        return preg_match($pattern, $ip);
    }

    /**
     * Build the two-line IP-location label drained out of
     * get_ip_location(): the resolved geo name (or a fallback "unknown"
     * label) plus the formatted "user IP" line.
     *
     * @return array{0:string,1:string}
     */
    public static function ipLocationLabels(
        ?string $geoName,
        string $ip,
        string $unknownLabel,
        string $ipLabelPrefix,
    ): array {
        return [
            ($geoName !== null && $geoName !== '') ? $geoName : $unknownLabel,
            $ipLabelPrefix.':&nbsp;'.trim($ip, ','),
        ];
    }
}
