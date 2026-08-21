<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StandardsTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('standards')->delete();

        DB::table('standards')->insert([
            0 => [
                'id' => 1,
                'name' => '1080p',
                'sort_index' => 0,
            ],
            1 => [
                'id' => 2,
                'name' => '1080i',
                'sort_index' => 0,
            ],
            2 => [
                'id' => 3,
                'name' => '720p',
                'sort_index' => 0,
            ],
            3 => [
                'id' => 4,
                'name' => 'SD',
                'sort_index' => 0,
            ],
        ]);

    }
}
