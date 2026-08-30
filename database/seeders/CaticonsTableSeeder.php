<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CaticonsTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('caticons')->delete();

        DB::table('caticons')->insert([
            0 => [
                'id' => 1,
                'name' => 'SceneTorrents mod',
                'folder' => 'scenetorrents/',
                'cssfile' => 'pic/category/chd/scenetorrents/catsprites.css',
                'multilang' => 1,
                'secondicon' => 0,
                'designer' => 'NexusPHP',
                'comment' => 'Modified from SceneTorrents',
            ],
        ]);

    }
}
