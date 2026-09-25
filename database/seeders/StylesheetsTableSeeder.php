<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StylesheetsTableSeeder extends Seeder
{
    /**
     * ADR 0019: the four legacy clone themes are removed; Classic stays
     * as the stylesheet fallback for legacy page bodies until stage 3.
     *
     * @return void
     */
    public function run()
    {

        DB::table('stylesheets')->delete();

        DB::table('stylesheets')->insert([
            0 => [
                'id' => 4,
                'uri' => 'styles/Classic/',
                'name' => 'Classic',
                'addicode' => '',
                'designer' => 'Zantetsu',
                'comment' => 'TBSource original mod',
            ],
            1 => [
                'id' => 5,
                'uri' => 'styles/Unshatter/',
                'name' => 'Unshatter',
                'addicode' => '',
                'designer' => 'Devin',
                'comment' => 'Linkin Park From Zero / Unshatter palette — black, white, yellow. Sharp corners, own light and dark variants.',
            ],
            2 => [
                'id' => 6,
                'uri' => 'styles/ThousandSuns/',
                'name' => 'Thousand Suns',
                'addicode' => '',
                'designer' => 'Devin',
                'comment' => 'Linkin Park A Thousand Suns palette — cold slate, indigo to cyan accent. Flat minimal look, own light and dark variants.',
            ],
        ]);

    }
}
