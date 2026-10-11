<?php

declare(strict_types=1);

namespace App\DTOs\Announce;

use App\Enums\UserClass;
use App\Support\UserDisplay;

/**
 * Typed tracker-user value object for the announce/scrape pipeline.
 *
 * Wraps the user row produced by PasskeyUserLookup / AnnounceUserRepository
 * and replaces the raw `array $user` (legacy CURUSER shape) that used to flow
 * through AnnounceService, CheaterDetector, HitAndRunHandler, PeerLimitGuard,
 * PeerLifecycle, TrafficAccountant and TorrentOps::dataTraffic.
 *
 * `toLegacyArray()` returns the underlying row — including the `__is_donor`
 * field TorrentOps::dataTraffic still validates — so call sites on the array
 * boundary keep working until they migrate.
 */
final readonly class AnnounceUser
{
    /**
     * @param  array<string, mixed>  $row
     */
    private function __construct(
        public int $id,
        public string $username,
        public int $classId,
        public bool $enabled,
        public bool $parked,
        public bool $downloadpos,
        public bool $showclienterror,
        public ?int $clientselect,
        public int|float $uploaded,
        public int|float $downloaded,
        public ?float $seedbonus,
        public ?int $trackerUrlId,
        public bool $isDonor,
        private array $row,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            username: (string) ($row['username'] ?? ''),
            classId: (int) ($row['class'] ?? 0),
            enabled: (bool) ($row['enabled'] ?? false),
            parked: (bool) ($row['parked'] ?? false),
            downloadpos: (bool) ($row['downloadpos'] ?? false),
            showclienterror: (bool) ($row['showclienterror'] ?? false),
            clientselect: isset($row['clientselect']) ? (int) $row['clientselect'] : null,
            uploaded: is_numeric($row['uploaded'] ?? null) ? $row['uploaded'] + 0 : 0,
            downloaded: is_numeric($row['downloaded'] ?? null) ? $row['downloaded'] + 0 : 0,
            seedbonus: isset($row['seedbonus']) ? (float) $row['seedbonus'] : null,
            trackerUrlId: isset($row['tracker_url_id']) ? (int) $row['tracker_url_id'] : null,
            isDonor: isset($row['donor']) && UserDisplay::isDonor($row),
            row: $row,
        );
    }

    public static function guest(): self
    {
        return self::fromRow([]);
    }

    /**
     * Raw row for array-boundary call sites (carries `__is_donor`, which
     * TorrentOps::dataTraffic requires).
     *
     * @return array<string, mixed>
     */
    public function toLegacyArray(): array
    {
        return $this->row + ['__is_donor' => $this->isDonor];
    }

    public function isVip(): bool
    {
        return $this->classId === (int) UserClass::VIP->value;
    }

    public function classAtLeast(int $class): bool
    {
        return $this->classId >= $class;
    }

    public function isVipOrAbove(): bool
    {
        return $this->classAtLeast((int) UserClass::VIP->value);
    }
}
