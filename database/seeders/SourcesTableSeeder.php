<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SourcesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('sources')->delete();

        DB::table('sources')->insert([
            0 => [
                'id' => 1,
                'name' => 'Blu-ray',
                'sort_index' => 0,
            ],
            1 => [
                'id' => 2,
                'name' => 'HD DVD',
                'sort_index' => 0,
            ],
            2 => [
                'id' => 3,
                'name' => 'DVD',
                'sort_index' => 0,
            ],
            3 => [
                'id' => 4,
                'name' => 'HDTV',
                'sort_index' => 0,
            ],
            4 => [
                'id' => 5,
                'name' => 'TV',
                'sort_index' => 0,
            ],
            5 => [
                'id' => 6,
                'name' => 'Other',
                'sort_index' => 0,
            ],
        ]);

    }
}
