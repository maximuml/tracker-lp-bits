<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguageTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('language')->delete();

        DB::table('language')->insert([
            0 => [
                'id' => 6,
                'lang_name' => 'English',
                'flagpic' => 'uk.gif',
                'sub_lang' => 1,
                'rule_lang' => 1,
                'site_lang' => 1,
                'site_lang_folder' => 'en',
                'trans_state' => 'up-to-date',
            ],
        ]);

    }
}
