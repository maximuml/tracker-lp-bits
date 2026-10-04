<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\LocationRepository;
use App\Support\Network;
use Illuminate\Support\Collection;

/**
 * Handles IP location CRUD mutations.
 *
 * Read-side (listing, range queries, edit form) stays in AdminToolsController.
 */
final class LocationService
{
    public function __construct(private readonly LocationRepository $locationRepository) {}

    /**
     * @param  array<string, string>  $data
     */
    public function createLocation(array $data): bool
    {
        $startIp = (string) ($data['start_ip'] ?? '');
        $endIp = (string) ($data['end_ip'] ?? '');

        if (! Network::isValidIpv4Format($startIp) || ! Network::isValidIpv4Format($endIp)) {
            return false;
        }
        if (ip2long($endIp) <= ip2long($startIp)) {
            return false;
        }

        $this->locationRepository->insert($this->locationPayload($data, $startIp, $endIp));

        return true;
    }

    /**
     * @param  array<string, string>  $data
     */
    public function updateLocation(int $id, array $data): bool
    {
        $startIp = (string) ($data['start_ip'] ?? '');
        $endIp = (string) ($data['end_ip'] ?? '');

        if (! Network::isValidIpv4Format($startIp) || ! Network::isValidIpv4Format($endIp)) {
            return false;
        }
        if (ip2long($endIp) <= ip2long($startIp)) {
            return false;
        }

        $this->locationRepository->updateById($id, $this->locationPayload($data, $startIp, $endIp));

        return true;
    }

    public function deleteLocation(int $id): void
    {
        $this->locationRepository->deleteById($id);
    }

    /**
     * @return array<string, mixed>|null
     */
    /**
     * Locations in an optional client-side IP range (ints from ip2long).
     */
    public function countLocations(?int $rangeStartIp = null, ?int $rangeEndIp = null): int
    {
        return (int) $this->locationRepository->newRangeQuery($rangeStartIp, $rangeEndIp)->count();
    }

    /**
     * @return Collection<int, \stdClass>
     */
    public function listLocations(int $offset, int $limit, ?int $rangeStartIp = null, ?int $rangeEndIp = null): Collection
    {
        return $this->locationRepository->newRangeQuery($rangeStartIp, $rangeEndIp)
            ->orderBy('name')
            ->orderBy('start_ip')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    /**
     * @param  array<string, string>  $data
     * @return array<string, string>
     */
    private function locationPayload(array $data, string $startIp, string $endIp): array
    {
        return [
            'name' => (string) ($data['name'] ?? ''),
            'flagpic' => (string) ($data['flagpic'] ?? ''),
            'location_main' => (string) ($data['location_main'] ?? ''),
            'location_sub' => (string) ($data['location_sub'] ?? ''),
            'start_ip' => $startIp,
            'end_ip' => $endIp,
            'theory_upspeed' => (string) ($data['theory_upspeed'] ?? ''),
            'practical_upspeed' => (string) ($data['practical_upspeed'] ?? ''),
            'theory_downspeed' => (string) ($data['theory_downspeed'] ?? ''),
            'practical_downspeed' => (string) ($data['practical_downspeed'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLocation(int $id): ?array
    {
        return $this->locationRepository->findById($id);
    }
}
