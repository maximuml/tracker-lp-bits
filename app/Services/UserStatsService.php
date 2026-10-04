<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Support\Config\SiteConfig;

/**
 * User statistics and aggregate query helpers.
 *
 * Extracted from UserRepository to reduce god-object surface area.
 * Covers seeding/leeching data calculations and user aggregate lookups.
 */
final class UserStatsService
{
    public function __construct(private readonly TorrentRepositoryInterface $torrentRepository) {}

    /**
     * get user seeding/leeching count and size
     *
     * @param  array<int|string, mixed>  $userIdArr
     * @return array<int|string, mixed>
     *
     * @see calculate_seed_bonus()
     */
    public function listUserSeedingLeechingData(array $userIdArr)
    {
        $minSize = SiteConfig::current()->bonus->minSize(0);
        $data = $this->torrentRepository->listSeedingLeechingForUsers($userIdArr, $minSize);
        $result = [];
        foreach ($data as $row) {
            $row = (array) $row;
            if (! isset($result[$row['userid']])) {
                $result[$row['userid']] = [
                    'seeding_count' => 0,
                    'seeding_size' => 0,
                    'leeching_count' => 0,
                    'leeching_size' => 0,
                ];
            }
            if ($row['seeder'] == 1) {
                $result[$row['userid']]['seeding_count'] += 1;
                $result[$row['userid']]['seeding_size'] += $row['size'];
            } else {
                $result[$row['userid']]['leeching_count'] += 1;
                $result[$row['userid']]['leeching_size'] += $row['size'];
            }
        }

        return $result;
    }
}
