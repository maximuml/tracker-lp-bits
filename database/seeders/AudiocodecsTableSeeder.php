<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AudiocodecsTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('audiocodecs')->delete();

        DB::table('audiocodecs')->insert([
            0 => [
                'id' => 1,
                'name' => 'FLAC',
                'image' => '',
                'sort_index' => 0,
            ],
            1 => [
                'id' => 2,
                'name' => 'APE',
                'image' => '',
                'sort_index' => 0,
            ],
            2 => [
                'id' => 3,
                'name' => 'DTS',
                'image' => '',
                'sort_index' => 0,
            ],
            3 => [
                'id' => 4,
                'name' => 'MP3',
                'image' => '',
                'sort_index' => 0,
            ],
            4 => [
                'id' => 5,
                'name' => 'OGG',
                'image' => '',
                'sort_index' => 0,
            ],
            5 => [
                'id' => 6,
                'name' => 'AAC',
                'image' => '',
                'sort_index' => 0,
            ],
            6 => [
                'id' => 7,
                'name' => 'Other',
                'image' => '',
                'sort_index' => 0,
            ],
        ]);

    }
}
