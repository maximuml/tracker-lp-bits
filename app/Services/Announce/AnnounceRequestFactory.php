<?php

declare(strict_types=1);

namespace App\Services\Announce;

use App\DTOs\AnnounceRequestDto;
use App\Support\Network;
use App\Support\Network\ClientIpResolver;
use App\Support\SupportContext;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use Illuminate\Http\Request;

/**
 * Factory that builds AnnounceRequestDto from a raw HTTP request.
 *
 * The side effect of populating SupportContext from the request lives
 * here, not in the DTO itself, so the DTO remains a pure immutable
 * data container that can be constructed in unit tests without
 * booting the Laravel container or touching global state.
 */
final class AnnounceRequestFactory
{
    public function __construct(
        private readonly ClientIpResolver $ipResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public function create(Request $request, array $params): AnnounceRequestDto
    {
        // Side effect: populate per-request SupportContext from the request.
        // This was previously inside AnnounceRequestDto::fromRequest().
        SupportContext::fromRequest($request);

        return $this->build($params, $request->header('User-Agent'));
    }

    /**
     * Build a DTO from pre-parsed params without touching global state.
     *
     * Useful in tests and in pipelines where SupportContext is already
     * populated.
     *
     * @param  array<string, mixed>  $params
     */
    public function build(array $params, ?string $userAgent = null): AnnounceRequestDto
    {
        $passkey = Passkey::fromString($params['passkey']);
        $infoHash = InfoHash::fromBinary($params['info_hash']);
        $peerId = PeerId::fromBinary($params['peer_id']);

        $port = (int) $params['port'];
        $uploaded = (int) $params['uploaded'];
        $downloaded = (int) $params['downloaded'];
        $left = (int) $params['left'];

        $event = $params['event'] ?? null;
        if ($event !== null && ! in_array($event, ['started', 'completed', 'stopped', 'paused'], true)) {
            $event = null;
        }

        $numWant = (int) ($params['numwant'] ?? $params['num_want'] ?? 50);
        if ($numWant < 0) {
            $numWant = 0;
        }
        if ($numWant > 200) {
            $numWant = 200;
        }

        $compact = ! empty($params['compact']);

        $ip = $this->ipResolver->resolve();

        $ipv4 = null;
        $ipv6 = null;
        if (Network::isIpv4($ip)) {
            $ipv4 = $ip;
        } elseif (Network::isIpv6($ip)) {
            $ipv6 = $ip;
        }

        if ($ipv4 === null && ! empty($params['ipv4']) && Network::isIpv4($params['ipv4'])) {
            $ipv4 = $params['ipv4'];
        }
        if ($ipv6 === null && ! empty($params['ipv6']) && Network::isIpv6($params['ipv6'])) {
            $ipv6 = $params['ipv6'];
        }

        return new AnnounceRequestDto(
            passkey: $passkey,
            infoHash: $infoHash,
            peerId: $peerId,
            port: $port,
            uploaded: $uploaded,
            downloaded: $downloaded,
            left: $left,
            event: $event,
            numWant: $numWant,
            compact: $compact,
            ipv4: $ipv4,
            ipv6: $ipv6,
            ip: $ip,
            userAgent: (string) $userAgent,
        );
    }
}
