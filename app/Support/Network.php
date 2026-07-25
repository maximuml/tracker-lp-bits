<?php

namespace App\Support;

/**
 * IP helpers extracted from `include/functions.php`.
 *
 * Phase 5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 5 — drain `include/functions.php`".
 * The legacy procedural helpers
 *
 *   - `in_ip_range($long, $targetip, $ip_one, $ip_two = false)`
 *   - `get_ip_location_from_geoip($ip)`
 *
 * collapse into the static methods below. The legacy functions now
 * proxy here.
 */
final class Network
{
    /**
     * Legacy `validip()` check. IPv6 addresses are considered valid,
     * IPv4 addresses must not fall inside the legacy reserved IANA ranges.
     */
    public static function isValid(?string $ip): bool
    {
        $ip = (string) $ip;
        if (!ip2long($ip)) {
            return true;
        }
        if (!empty($ip) && $ip == long2ip(ip2long($ip))) {
            $reservedIps = [
                ['192.0.2.0', '192.0.2.255'],
                ['192.168.0.0', '192.168.255.255'],
                ['255.255.255.0', '255.255.255.255'],
            ];
            $ipLong = ip2long($ip);
            foreach ($reservedIps as $r) {
                $min = ip2long($r[0]);
                $max = ip2long($r[1]);
                if ($ipLong >= $min && $ipLong <= $max) {
                    return false;
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Legacy `getip()` resolver. Picks the first valid IP from
     * `HTTP_X_FORWARDED_FOR`, `HTTP_CLIENT_IP` or `REMOTE_ADDR`,
     * then optionally trims to the first comma-separated value.
     */
    public static function clientIp(bool $real = true): string
    {
        if (($forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? getenv('HTTP_X_FORWARDED_FOR')) && self::isValid($forwarded)) {
            $ip = $forwarded;
        } elseif (($client = $_SERVER['HTTP_CLIENT_IP'] ?? getenv('HTTP_CLIENT_IP')) && self::isValid($client)) {
            $ip = $client;
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? getenv('REMOTE_ADDR') ?: '';
        }

        $ip = trim(trim((string) $ip), ',');
        if ($real && str_contains($ip, ',')) {
            return (string) strstr($ip, ',', true);
        }

        return $ip;
    }

    /**
     * Legacy `isIPV4()` / `isIPV6()` checks using `filter_var()`.
     */
    public static function isIpv4(?string $ip): bool
    {
        return (bool) filter_var((string) $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
    }

    public static function isIpv6(?string $ip): bool
    {
        return (bool) filter_var((string) $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
    }

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

    /**
     * Resolve GeoIP2 data for an IP address.
     *
     * Mirrors `get_ip_location_from_geoip()`. The result is cached for
     * 10 days via `NexusDB::remember()`. Returns `false` when the GeoIP2
     * database is missing/unreadable.
     *
     * @return array<string, mixed>|false
     */
    public static function geoIpInfo(string $ip): array|false
    {
        $locationInfo = \Nexus\Database\NexusDB::remember("locations_{$ip}", 864000, function () use ($ip) {
            $lang = \get_langfolder_cookie();
            $langMap = [
                'chs' => 'zh-CN',
                'cht' => 'zh-CN',
                'en' => 'en',
            ];
            $locale = $langMap[$lang] ?? $lang;
            $info = [
                'ip' => $ip,
                'version' => '',
                'country' => '',
                'city' => '',
                'country_en' => '',
                'city_en' => '',
                'continent_en' => '',
            ];

            try {
                $database = \nexus_env('GEOIP2_DATABASE');
                if (empty($database)) {
                    \do_log('no geoip2 database.');

                    return false;
                }
                if (! is_readable($database)) {
                    \do_log("geoip2 database: $database is not readable.");

                    return false;
                }

                $reader = new \GeoIp2\Database\Reader($database);
                $record = $reader->city($ip);
                $countryName = $record->country->names[$locale] ?? $record->country->names['en'] ?? '';
                $cityName = $record->city->names[$locale] ?? $record->city->names['en'] ?? '';
                $continentName = $record->continent->names[$locale] ?? $record->continent->names['en'] ?? '';

                if (self::isIpv4($ip)) {
                    $info['version'] = 4;
                } elseif (self::isIpv6($ip)) {
                    $info['version'] = 6;
                }

                $info['country'] = $countryName;
                $info['country_en'] = $record->country->names['en'] ?? '';
                $info['city'] = $cityName;
                $info['city_en'] = $record->city->names['en'] ?? '';
                $info['continent'] = $continentName;
                $info['continent_en'] = $record->continent->names['en'] ?? '';
            } catch (\Exception $exception) {
                \do_log($exception->getMessage() . ', trace: ' . $exception->getTraceAsString(), 'error');
            }

            return $info;
        });

        \do_log('ip: ' . $ip . ', result: ' . \nexus_json_encode($locationInfo));

        if ($locationInfo === false) {
            return false;
        }

        $name = sprintf('%s[v%s]', $locationInfo['city'] ? ($locationInfo['city'] . '·' . $locationInfo['country']) : $locationInfo['country'], $locationInfo['version']);

        return [
            'name' => $name,
            'location_main' => '',
            'location_sub' => '',
            'flagpic' => '',
            'start_ip' => $ip,
            'end_ip' => $ip,
            'ip_version' => $locationInfo['version'],
            'country_en' => $locationInfo['country_en'],
            'city_en' => $locationInfo['city_en'],
            'continent_en' => $locationInfo['continent_en'],
        ];
    }

    /**
     * Resolve an IP to a two-element location label array, with an
     * in-request static cache.
     *
     * Mirrors `get_ip_location()`.
     *
     * @return array{0:string,1:string}
     */
    public static function ipLocation(string $ip, string $unknownLabel, string $userIpLabel): array
    {
        static $locations = [];

        if (isset($locations[$ip])) {
            return $locations[$ip];
        }

        $geoName = self::geoIpInfo($ip)['name'] ?? null;

        return $locations[$ip] = self::ipLocationLabels($geoName, $ip, $unknownLabel, $userIpLabel);
    }
}
