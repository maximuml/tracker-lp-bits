<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds lookup tables that previously had INSERT data in
 * _db/dbstructure_v1.6.sql but were not in the installer's
 * $initializeTables list. Now that migrations create these tables,
 * the seed data must come from a seeder instead of the SQL file.
 */
class LegacyLookupTableSeeder extends Seeder
{
    public function run(): void
    {
        $speeds = [
            '64kbps', '128kbps', '256kbps', '512kbps', '768kbps',
            '1Mbps', '1.5Mbps', '2Mbps', '3Mbps', '4Mbps',
            '5Mbps', '6Mbps', '7Mbps', '8Mbps', '9Mbps',
            '10Mbps', '48Mbps', '100Mbit',
        ];

        if (DB::table('downloadspeed')->count() === 0) {
            foreach ($speeds as $i => $name) {
                DB::table('downloadspeed')->insert(['id' => $i + 1, 'name' => $name]);
            }
        }

        if (DB::table('uploadspeed')->count() === 0) {
            foreach ($speeds as $i => $name) {
                DB::table('uploadspeed')->insert(['id' => $i + 1, 'name' => $name]);
            }
        }

        if (DB::table('isp')->count() === 0) {
            $isps = [
                1 => '中国电信', 2 => '中国网通', 3 => '中国铁通',
                4 => '中国移动', 5 => '中国联通', 6 => '中国教育网',
                20 => 'Other',
            ];
            foreach ($isps as $id => $name) {
                DB::table('isp')->insert(['id' => $id, 'name' => $name]);
            }
        }

        if (DB::table('teams')->count() === 0) {
            $teams = ['HDS', 'CHD', 'MySiLU', 'WiKi', 'Other'];
            foreach ($teams as $i => $name) {
                DB::table('teams')->insert(['id' => $i + 1, 'name' => $name, 'sort_index' => 0]);
            }
        }

        // schools table has 100 entries — skip seeding by default.
        // Operators can import school data manually if needed.
    }
}
