<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\Network;
use Illuminate\Support\Facades\DB;

/**
 * Handles IP location CRUD mutations.
 *
 * Read-side (listing, range queries, edit form) stays in AdminToolsController.
 */
final class LocationService
{
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

        DB::table('locations')->insert([
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
        ]);

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

        DB::table('locations')->where('id', $id)->update([
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
        ]);

        return true;
    }

    public function deleteLocation(int $id): void
    {
        DB::table('locations')->where('id', $id)->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLocation(int $id): ?array
    {
        $row = DB::table('locations')->where('id', $id)->first();

        return $row ? (array) $row : null;
    }
}
