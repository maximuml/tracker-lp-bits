<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProcessingsTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('processings')->delete();

        DB::table('processings')->insert([
            0 => [
                'id' => 1,
                'name' => 'Raw',
                'sort_index' => 0,
            ],
            1 => [
                'id' => 2,
                'name' => 'Encode',
                'sort_index' => 0,
            ],
        ]);

    }
}
