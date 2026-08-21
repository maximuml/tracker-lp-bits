<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TagsTableSeeder extends Seeder
{
    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {

        DB::table('tags')->delete();

        DB::table('tags')->insert([
            0 => [
                'id' => 1,
                'name' => '禁转',
                'color' => '#ff0000',
                'priority' => 7,
                'created_at' => '2022-03-10 01:46:44',
                'updated_at' => '2022-03-10 01:46:44',
            ],
            1 => [
                'id' => 2,
                'name' => '首发',
                'color' => '#8F77B5',
                'priority' => 6,
                'created_at' => '2022-03-10 01:46:44',
                'updated_at' => '2022-03-10 01:46:44',
            ],
            2 => [
                'id' => 3,
                'name' => '官方',
                'color' => '#0000ff',
                'priority' => 5,
                'created_at' => '2022-03-10 01:46:44',
                'updated_at' => '2022-03-10 01:46:44',
            ],
            3 => [
                'id' => 4,
                'name' => 'DIY',
                'color' => '#46d5ff',
                'priority' => 4,
                'created_at' => '2022-03-10 01:46:44',
                'updated_at' => '2022-03-10 01:46:44',
            ],
            4 => [
                'id' => 5,
                'name' => '国语',
                'color' => '#6a3906',
                'priority' => 3,
                'created_at' => '2022-03-10 01:46:44',
                'updated_at' => '2022-03-10 01:46:44',
            ],
            5 => [
                'id' => 6,
                'name' => '中字',
                'color' => '#006400',
                'priority' => 2,
                'created_at' => '2022-03-10 01:46:44',
                'updated_at' => '2022-03-10 01:46:44',
            ],
            6 => [
                'id' => 7,
                'name' => 'HDR',
                'color' => '#38b03f',
                'priority' => 1,
                'created_at' => '2022-03-10 01:46:44',
                'updated_at' => '2022-03-10 01:46:44',
            ],
        ]);

    }
}
