<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModpanelTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('modpanel')->delete();

        DB::table('modpanel')->insert([
            0 => [
                'id' => 1,
                'name' => 'Abnormal Upload Speed Detector',
                'url' => 'cheaters.php',
                'info' => 'See cheaters',
            ],
            1 => [
                'id' => 2,
                'name' => 'Duplicate IP Check',
                'url' => 'ipcheck.php',
                'info' => 'Check for Duplicate IP Users',
            ],
            2 => [
                'id' => 3,
                'name' => 'All Clients (currently)',
                'url' => 'allagents.php',
                'info' => 'Show All Clients (currently downloading/uploading/seeding)',
            ],
            4 => [
                'id' => 5,
                'name' => 'Uploaders',
                'url' => 'uploaders.php',
                'info' => 'See uploaders stats',
            ],
            5 => [
                'id' => 6,
                'name' => 'Stats',
                'url' => 'stats.php',
                'info' => 'Tracker Stats',
            ],
            6 => [
                'id' => 7,
                'name' => 'IP Test',
                'url' => 'testip.php',
                'info' => 'Test if IP is banned',
            ],
            //            7 =>
            //            array (
            //                'id' => 8,
            //                'name' => 'Add Bonus Points',
            //                'url' => 'amountbonus.php',
            //                'info' => 'Add Bonus Points to one or All Users.',
            //            ),
            8 => [
                'id' => 9,
                'name' => 'Clear cache',
                'url' => 'clearcache.php',
                'info' => 'Clear cache of memcached',
            ],
            9 => [
                'id' => 10,
                'name' => 'Search user', 'url' => 'usersearch.php', 'info' => 'Search user',
            ],
            10 => [
                'id' => 11,
                'name' => 'Confirm user', 'url' => 'unco.php', 'info' => 'Confirm user to complete registration',
            ],
        ]);

    }
}
