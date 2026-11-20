<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategoriesTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('categories')->delete();

        DB::table('categories')->insert([
            0 => [
                'id' => 404,
                'mode' => 4,
                'class_name' => 'c_doc',
                'name' => 'Documentaries \\ Misc',
                'image' => 'catsprites.png',
                'sort_index' => 5,
                'icon_id' => 1,
            ],
            1 => [
                'id' => 406,
                'mode' => 4,
                'class_name' => 'c_mv',
                'name' => 'Music Videos',
                'image' => 'catsprites.png',
                'sort_index' => 4,
                'icon_id' => 1,
            ],
            2 => [
                'id' => 407,
                'mode' => 4,
                'class_name' => 'c_hqaudio',
                'name' => 'Audio Professional',
                'image' => 'catsprites.png',
                'sort_index' => 3,
                'icon_id' => 1,
            ],
            3 => [
                'id' => 408,
                'mode' => 4,
                'class_name' => 'c_proshot',
                'name' => 'Pro Shot Video',
                'image' => 'catsprites.png',
                'sort_index' => 2,
                'icon_id' => 1,
            ],
            4 => [
                'id' => 409,
                'mode' => 4,
                'class_name' => 'c_vidaud',
                'name' => 'Video Audience Shot',
                'image' => 'catsprites.png',
                'sort_index' => 1,
                'icon_id' => 1,
            ],
            5 => [
                'id' => 410,
                'mode' => 4,
                'class_name' => 'c_audaud',
                'name' => 'Audio Audience Shot',
                'image' => 'catsprites.png',
                'sort_index' => 0,
                'icon_id' => 1,
            ],
        ]);

    }
}
