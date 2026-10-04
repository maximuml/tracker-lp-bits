<?php

namespace Database\Seeders;

use App\Support\Cache\LegacyRedisCache;
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
        ]);

        // Style::cssRow() serves the stylesheets table from a ~26h
        // 'stylesheet_content' cache — drop it after the rewrite so a blob
        // cached before seeding does not hide the re-inserted rows.
        try {
            app(LegacyRedisCache::class)->delete_value('stylesheet_content');
        } catch (\Throwable) {
            // non-critical: the entry expires on its own
        }

    }
}
