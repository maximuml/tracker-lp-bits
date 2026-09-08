<?php

declare(strict_types=1);

namespace App\DTOs;

use App\ValueObjects\InfoHash;
use App\ValueObjects\Passkey;
use App\ValueObjects\PeerId;

/**
 * Immutable DTO for a BitTorrent announce request.
 *
 * This is a pure data container — no global state, no side effects.
 * Construction from a raw HTTP request is handled by
 * App\Services\Announce\AnnounceRequestFactory, which populates
 * SupportContext and resolves the client IP before building the DTO.
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
