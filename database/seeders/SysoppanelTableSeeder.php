<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SysoppanelTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('sysoppanel')->delete();

        DB::table('sysoppanel')->insert([
            //            0 =>
            //            array (
            //                'id' => 1,
            //                'name' => 'Delete disabled users',
            //                'url' => 'deletedisabled.php',
            //                'info' => 'Delete all disabled users',
            //            ),
            1 => [
                'id' => 2,
                'name' => 'Manage tracker forum',
                'url' => 'forummanage.php',
                'info' => 'Edit/Delete forum',
            ],
            2 => [
                'id' => 3,
                'name' => 'MySQL Stats',
                'url' => 'mysql_stats.php',
                'info' => 'See MySql stats',
            ],
            3 => [
                'id' => 4,
                'name' => 'Mass mailer',
                'url' => 'massmail.php',
                'info' => 'Send e-mail to all users on the tracker',
            ],
            4 => [
                'id' => 5,
                'name' => 'Do cleanup',
                'url' => 'docleanup.php',
                'info' => 'Do cleanup functions',
            ],
            5 => [
                'id' => 6,
                'name' => 'Ban System',
                'url' => 'bans.php',
                'info' => 'Ban / Unban IP',
            ],
            6 => [
                'id' => 7,
                'name' => 'Failed Logins',
                'url' => 'maxlogin.php',
                'info' => 'Show Failed Login Attempts',
            ],
            7 => [
                'id' => 8,
                'name' => 'Bitbucket',
                'url' => 'bitbucketlog.php',
                'info' => 'Bitbucket Log',
            ],
            8 => [
                'id' => 11,
                'name' => 'Location',
                'url' => 'location.php',
                'info' => 'Manage location and location speed',
            ],
            11 => [
                'id' => 12,
                'name' => 'Add Bonus/Attend card/Invite/upload',
                'url' => 'increment-bulk.php',
                'info' => 'Add Bonus/Attend card/Invite/upload to certain classes',
            ],
        ]);

    }
}
