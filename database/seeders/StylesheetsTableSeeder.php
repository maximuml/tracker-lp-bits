<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StylesheetsTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('stylesheets')->delete();

        DB::table('stylesheets')->insert([
            0 => [
                'id' => 2,
                'uri' => 'styles/BlueGene/',
                'name' => 'Blue Gene',
                'addicode' => '',
                'designer' => 'Zantetsu',
                'comment' => 'HDBits clone',
            ],
            1 => [
                'id' => 3,
                'uri' => 'styles/BlasphemyOrange/',
                'name' => 'Blasphemy Orange',
                'addicode' => '',
                'designer' => 'Zantetsu',
                'comment' => 'Bit-HDTV clone',
            ],
            2 => [
                'id' => 4,
                'uri' => 'styles/Classic/',
                'name' => 'Classic',
                'addicode' => '',
                'designer' => 'Zantetsu',
                'comment' => 'TBSource original mod',
            ],
            3 => [
                'id' => 6,
                'uri' => 'styles/DarkPassion/',
                'name' => 'Dark Passion',
                'addicode' => '',
                'designer' => 'Zantetsu',
                'comment' => '',
            ],
            4 => [
                'id' => 7,
                'uri' => 'styles/BambooGreen/',
                'name' => 'Bamboo Green',
                'addicode' => '',
                'designer' => 'Xia Zuojie',
                'comment' => 'Baidu Hi clone',
            ],
        ]);

    }
}
