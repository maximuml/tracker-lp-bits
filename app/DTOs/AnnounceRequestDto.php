<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Support\Network;
use App\Support\SupportContext;
use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;
use Illuminate\Http\Request;

/**
 * Immutable DTO for a BitTorrent announce request.
 *
 * Centralises parsing of the request/params, IP resolution, and value-object
 * creation so the announce pipeline receives a typed, validated input.
 */
final readonly class AnnounceRequestDto
{
    public function __construct(
        public Passkey $passkey,
        public InfoHash $infoHash,
        public PeerId $peerId,
        public int $port,
        public int $uploaded,
        public int $downloaded,
        public int $left,
        public ?string $event,
        public int $numWant,
        public bool $compact,
        public ?string $ipv4,
        public ?string $ipv6,
        public string $ip,
        public string $userAgent,
    ) {}

    public static function fromRequest(Request $request, array $params): self
    {
        SupportContext::fromRequest($request);

        $passkey = Passkey::fromString($params['passkey']);
        $infoHash = InfoHash::fromBinary($params['info_hash']);
        $peerId = PeerId::fromBinary($params['peer_id']);

        $port = (int) $params['port'];
        $uploaded = (int) $params['uploaded'];
        $downloaded = (int) $params['downloaded'];
        $left = (int) $params['left'];

        $event = $params['event'] ?? null;
        if ($event !== null && !in_array($event, ['started', 'completed', 'stopped', 'paused'], true)) {
            $event = null;
        }

        $numWant = (int) ($params['numwant'] ?? $params['num_want'] ?? 50);
        if ($numWant < 0) {
            $numWant = 0;
        }
        if ($numWant > 200) {
            $numWant = 200;
        }

        $compact = !empty($params['compact']);

        $ip = Network::clientIp(true);

        $ipv4 = null;
        $ipv6 = null;
        if (Network::isIpv4($ip)) {
            $ipv4 = $ip;
        } elseif (Network::isIpv6($ip)) {
            $ipv6 = $ip;
        }

        if ($ipv4 === null && !empty($params['ipv4']) && Network::isIpv4($params['ipv4'])) {
            $ipv4 = $params['ipv4'];
        }
        if ($ipv6 === null && !empty($params['ipv6']) && Network::isIpv6($params['ipv6'])) {
            $ipv6 = $params['ipv6'];
        }

        $userAgent = (string) $request->header('User-Agent');

        return new self(
            $passkey,
            $infoHash,
            $peerId,
            $port,
            $uploaded,
            $downloaded,
            $left,
            $event,
            $numWant,
            $compact,
            $ipv4,
            $ipv6,
            $ip,
            $userAgent,
        );
    }

    public function isSeeder(): bool
    {
        return $this->left === 0;
    }

    public function isStopped(): bool
    {
        return $this->event === 'stopped';
    }

    public function isCompleted(): bool
    {
        return $this->event === 'completed';
    }

    public function isStoppedOrCompleted(): bool
    {
        return $this->isStopped() || $this->isCompleted();
    }

    /**
     * @return array<string, mixed>
     */
    public function toParams(): array
    {
        return [
            'passkey' => $this->passkey->toString(),
            'info_hash' => $this->infoHash->toBinary(),
            'peer_id' => $this->peerId->toBinary(),
            'port' => $this->port,
            'uploaded' => $this->uploaded,
            'downloaded' => $this->downloaded,
            'left' => $this->left,
            'event' => $this->event,
            'numwant' => $this->numWant,
            'num_want' => $this->numWant,
            'ipv4' => $this->ipv4,
            'ipv6' => $this->ipv6,
            'compact' => $this->compact,
            'ip' => $this->ip,
        ];
    }
}
