<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Exceptions\TrackerException;
use App\Support\Network;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use Illuminate\Http\Request;

/**
 * Immutable DTO for a BitTorrent scrape request.
 *
 * Centralises passkey validation, browser blocking, info-hash parsing and
 * IP resolution so the scrape pipeline works with typed inputs.
 */
final readonly class ScrapeRequestDto
{
    /**
     * @param  list<InfoHash>  $infoHashes
     */
    public function __construct(
        public Passkey $passkey,
        public array $infoHashes,
        public string $userAgent,
        public string $ip,
        public ?string $ipv4,
        public ?string $ipv6,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $userAgent = (string) $request->header('User-Agent');
        self::blockBrowser($userAgent);
        self::blockCheater($request);

        $passkeyString = (string) $request->input('passkey', '');
        if ($passkeyString === '') {
            throw TrackerException::failure('require passkey');
        }

        $passkey = Passkey::fromString($passkeyString);

        $infoHashes = self::parseInfoHashes($request);

        $ip = Network::clientIp(true);
        $ipv4 = null;
        $ipv6 = null;
        if (Network::isIpv4($ip)) {
            $ipv4 = $ip;
        } elseif (Network::isIpv6($ip)) {
            $ipv6 = $ip;
        }

        return new self($passkey, $infoHashes, $userAgent, $ip, $ipv4, $ipv6);
    }

    /**
     * @return list<InfoHash>
     */
    private static function parseInfoHashes(Request $request): array
    {
        $queryString = (string) $request->server->get('QUERY_STRING', '');
        if ($queryString === '') {
            $queryString = (string) $request->getQueryString();
        }

        preg_match_all('/info_hash=([^&]*)/i', $queryString, $matches);

        $hashes = [];
        foreach (array_values(array_filter(array_map('urldecode', $matches[1]))) as $binary) {
            $infoHash = InfoHash::tryFromBinary($binary);
            if ($infoHash !== null) {
                $hashes[] = $infoHash;
            }
        }

        return $hashes;
    }

    private static function blockBrowser(string $userAgent): void
    {
        if (preg_match('/^(Mozilla|Opera|Links|Lynx)/', $userAgent)) {
            throw TrackerException::failure('Browser access blocked!');
        }
    }

    private static function blockCheater(Request $request): void
    {
        $https = $request->server('HTTPS');
        if ($https !== null && $https !== 'on') {
            $headers = $request->headers->all();
            if (isset($headers['cookie']) || isset($headers['accept-language']) || isset($headers['accept-charset'])) {
                throw TrackerException::failure('Anti-Cheater: You cannot use this agent');
            }
        }
    }
}
