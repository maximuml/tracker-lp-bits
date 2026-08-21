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
                'id' => 401,
                'mode' => 4,
                'class_name' => 'c_movies',
                'name' => 'Movies',
                'image' => 'catsprites.png',
                'sort_index' => 8,
                'icon_id' => 1,
            ],
            1 => [
                'id' => 402,
                'mode' => 4,
                'class_name' => 'c_tvseries',
                'name' => 'TV Series',
                'image' => 'catsprites.png',
                'sort_index' => 7,
                'icon_id' => 1,
            ],
            2 => [
                'id' => 403,
                'mode' => 4,
                'class_name' => 'c_tvshows',
                'name' => 'TV Shows',
                'image' => 'catsprites.png',
                'sort_index' => 6,
                'icon_id' => 1,
            ],
            3 => [
                'id' => 404,
                'mode' => 4,
                'class_name' => 'c_doc',
                'name' => 'Documentaries',
                'image' => 'catsprites.png',
                'sort_index' => 5,
                'icon_id' => 1,
            ],
            4 => [
                'id' => 405,
                'mode' => 4,
                'class_name' => 'c_anime',
                'name' => 'Animations',
                'image' => 'catsprites.png',
                'sort_index' => 4,
                'icon_id' => 1,
            ],
            5 => [
                'id' => 406,
                'mode' => 4,
                'class_name' => 'c_mv',
                'name' => 'Music Videos',
                'image' => 'catsprites.png',
                'sort_index' => 3,
                'icon_id' => 1,
            ],
            6 => [
                'id' => 407,
                'mode' => 4,
                'class_name' => 'c_sports',
                'name' => 'Sports',
                'image' => 'catsprites.png',
                'sort_index' => 2,
                'icon_id' => 1,
            ],
            7 => [
                'id' => 408,
                'mode' => 4,
                'class_name' => 'c_hqaudio',
                'name' => 'HQ Audio',
                'image' => 'catsprites.png',
                'sort_index' => 1,
                'icon_id' => 1,
            ],
            8 => [
                'id' => 409,
                'mode' => 4,
                'class_name' => 'c_misc',
                'name' => 'Misc',
                'image' => 'catsprites.png',
                'sort_index' => 0,
                'icon_id' => 1,
            ],
        ]);

    }
}
