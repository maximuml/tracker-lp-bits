<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TorrentsStateTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('torrents_state')->delete();

        DB::table('torrents_state')->insert([
            0 => [
                'global_sp_state' => 1,
            ],
        ]);

    }
}
