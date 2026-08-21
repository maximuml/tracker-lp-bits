<?php

declare(strict_types=1);

namespace App\Services\Announce;

/**
 * Result object for peer lifecycle operations.
 *
 * @property-read array<string, mixed> $torrentUpdate
 * @property-read array<string, mixed>|false $snatchInfo
 * @property-read array<string, mixed>|null $self
 */
final readonly class PeerLifecycleResult
{
    /**
     * @param  array<string, mixed>  $torrentUpdate
     * @param  array<string, mixed>|false  $snatchInfo
     * @param  array<string, mixed>|null  $self
     */
    public function __construct(
        public array $torrentUpdate,
        public array|false $snatchInfo,
        public ?array $self,
    ) {}
}
