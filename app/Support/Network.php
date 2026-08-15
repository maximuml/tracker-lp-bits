<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\IpUtils;

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
    /** @var array<int, string>|null */
    private static ?array $trustedProxies = null;

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
     * Legacy `getip()` resolver. Only trusts `X-Forwarded-For` and
     * `Client-Ip` headers when the direct peer (`REMOTE_ADDR`) is a
     * configured trusted proxy. The forwarded chain is walked from right
     * to left, skipping trusted proxies, so that a client cannot inject
     * an arbitrary leftmost address. This matches Symfony/Laravel's
     * `Request::ip()` behaviour.
     */
    public static function clientIp(bool $real = true): string
    {
        $remoteAddr = self::serverVar('REMOTE_ADDR');
        $trusted = self::getTrustedProxies();

        if (!self::isIpTrusted($remoteAddr, $trusted)) {
            return self::normalizeIp($remoteAddr, $real);
        }

        $chainTrusted = self::getChainTrustedProxies($remoteAddr, $trusted);

        if ($forwarded = self::serverVar('HTTP_X_FORWARDED_FOR')) {
            $ip = self::resolveForwardedClientIp($forwarded, $real, $chainTrusted) ?? $remoteAddr;
        } elseif (($client = self::serverVar('HTTP_CLIENT_IP')) && self::isValidIpFormat($client) && !self::isIpTrusted($client, $chainTrusted)) {
            $ip = $client;
        } else {
            $ip = $remoteAddr;
        }

        return self::normalizeIp($ip, $real);
    }

    private static function normalizeIp(string $ip, bool $real): string
    {
        $ip = trim($ip, ',');
        if ($real && str_contains($ip, ',')) {
            return (string) strstr($ip, ',', true);
        }

        return $ip;
    }

    /**
     * Override the configured trusted proxies. Intended for unit tests.
     *
     * @param  array<int, string>|null  $proxies
     */
    public static function setTrustedProxies(?array $proxies): void
    {
        self::$trustedProxies = $proxies;
    }

    /**
     * @return array<int, string>
     */
    private static function getTrustedProxies(): array
    {
        if (self::$trustedProxies !== null) {
            return self::$trustedProxies;
        }

        $config = \App\Support\Env::get('TRUSTED_PROXIES', '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,127.0.0.1,::1');
        if ($config === '*') {
            return ['*'];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $config))));
    }

    /**
     * @param  array<int, string>  $trusted
     */
    private static function isIpTrusted(string $ip, array $trusted): bool
    {
        if ($ip === '') {
            return false;
        }

        if ($trusted === ['*']) {
            return true;
        }

        if ($trusted === []) {
            return false;
        }

        return IpUtils::checkIp($ip, $trusted);
    }

    /**
     * @param  array<int, string>  $trusted
     * @return array<int, string>
     */
    private static function getChainTrustedProxies(string $remoteAddr, array $trusted): array
    {
        if ($trusted === ['*'] && $remoteAddr !== '') {
            // Trust only the direct peer; the chain beyond it must still be
            // inspected to discard any entries added by that proxy.
            return [$remoteAddr];
        }

        return $trusted;
    }

    /**
     * @param  array<int, string>  $trusted
     */
    private static function resolveForwardedClientIp(string $forwarded, bool $real, array $trusted): ?string
    {
        $ips = array_map('trim', explode(',', $forwarded));

        for ($i = count($ips) - 1; $i >= 0; $i--) {
            $candidate = $ips[$i];
            if ($candidate === '') {
                continue;
            }

            // Reject malformed chain entries rather than skipping them; skipping
            // could allow an attacker-controlled entry further left to be chosen.
            if (!self::isValidIpFormat($candidate)) {
                return null;
            }

            if (self::isIpTrusted($candidate, $trusted)) {
                continue;
            }

            if ($real) {
                return $candidate;
            }

            return $forwarded;
        }

        return null;
    }

    private static function isValidIpFormat(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    private static function serverVar(string $key): string
    {
        $value = SupportContext::getServerValue($key);
        if ($value === null) {
            $env = getenv($key);
            $value = is_string($env) ? $env : '';
        }

        return is_string($value) ? $value : '';
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
            $lang = \App\Support\Locale::folderFromCookie(\App\Support\SupportContext::getCookieValue('c_lang_folder', ''), (bool) false);
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
                $database = \App\Support\Env::get('GEOIP2_DATABASE', null);
                if (empty($database)) {
                    Logger::writeWithContext('no geoip2 database.');

                    return false;
                }
                if (! is_readable($database)) {
                    Logger::writeWithContext("geoip2 database: $database is not readable.");

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
                Logger::writeWithContext($exception->getMessage() . ', trace: ' . $exception->getTraceAsString(), 'error');
            }

            return $info;
        });

        Logger::writeWithContext('ip: ' . $ip . ', result: ' . Json::encode($locationInfo));

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
     * Check whether an IP address belongs to a known seed-box ASN.
     *
     * Mirrors `isIPSeedBoxFromASN()`. Uses the GeoIP2 ASN database and
     * Redis caching; may throw `SeedBoxYesException` when
     * `$exceptionWhenYes` is true and a match is found.
     */
    public static function isSeedBoxFromASN(string $ip, bool $exceptionWhenYes = false): bool
    {
        $redis = \Nexus\Database\NexusDB::redis();
        $key = 'nexus_asn';
        $notFoundCacheValue = '__NOT_FOUND__';
        $id = null;

        try {
            $database = \App\Support\Env::get('GEOIP2_ASN_DATABASE', null);
            if (!file_exists($database) || !is_readable($database)) {
                Logger::writeWithContext("GEOIP2_ASN_DATABASE: $database not exists or not readable", 'debug');
                return false;
            }

            static $reader;
            if (is_null($reader)) {
                $reader = new \GeoIp2\Database\Reader($database);
            }

            $asnObj = $reader->asn($ip);
            $asn = $asnObj->autonomousSystemNumber;
            if ($asn <= 0) {
                return false;
            }

            $cacheResult = $redis->hGet($key, $asn);
            if ($cacheResult !== false) {
                return $cacheResult !== $notFoundCacheValue;
            }

            $id = \App\Repositories\SeedBoxRepository::findIdByAsn($asn);
            if ($id !== null) {
                $redis->hSet($key, $asn, $id);
            } else {
                $redis->hSet($key, $asn, $notFoundCacheValue);
            }
        } catch (\Throwable $throwable) {
            Logger::writeWithContext("ip: $ip, " . $throwable->getMessage());
            if (isset($asn)) {
                $redis->hSet($key, $asn, $notFoundCacheValue);
            }
        }

        $result = $id !== null;
        if ($result && $exceptionWhenYes) {
            throw new \App\Exceptions\SeedBoxYesException($id);
        }

        return $result;
    }

    /**
     * Check whether an IP is a registered seed-box for a given user.
     *
     * Mirrors `isIPSeedBox()`.
     */
    public static function isSeedBox(string $ip, int $uid): bool
    {
        return \App\Repositories\SeedBoxRepository::isSeedBoxFromUserRecords($uid, $ip)['result'];
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

    /**
     * Locale-aware wrapper for {@see ipLocation()}, mirroring the legacy
     * `get_ip_location()` helper.
     *
     * @return array{0:string,1:string}
     */
    public static function ipLocationWithContext(string $ip): array
    {
        $lang_functions = \App\Support\SupportContext::getLangFunctions();

        return self::ipLocation(
            $ip,
            (string) ($lang_functions['text_unknown'] ?? ''),
            (string) ($lang_functions['text_user_ip'] ?? 'User IP'),
        );
    }
}
