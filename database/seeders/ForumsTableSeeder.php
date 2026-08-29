<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ForumsTableSeeder extends Seeder
{
    public function run()
    {
        // Overforums (top-level categories)
        $overforums = [
            ['id' => 1, 'name' => 'Main', 'description' => 'Main discussion area', 'minclassview' => 0, 'sort' => 1],
            ['id' => 2, 'name' => 'Torrents', 'description' => 'Torrent-related discussions', 'minclassview' => 0, 'sort' => 2],
        ];

        foreach ($overforums as $of) {
            DB::table('overforums')->updateOrInsert(['id' => $of['id']], $of);
        }

        // Forums (under overforums)
        $forums = [
            ['id' => 1, 'sort' => 1, 'name' => 'General Discussion', 'description' => 'General discussion forum', 'minclassread' => 0, 'minclasswrite' => 1, 'minclasscreate' => 1, 'forid' => 1],
            ['id' => 2, 'sort' => 2, 'name' => 'Movie Talk', 'description' => 'Discuss movies here', 'minclassread' => 0, 'minclasswrite' => 1, 'minclasscreate' => 1, 'forid' => 1],
            ['id' => 3, 'sort' => 3, 'name' => 'Help & Support', 'description' => 'Get help here', 'minclassread' => 0, 'minclasswrite' => 1, 'minclasscreate' => 1, 'forid' => 1],
            ['id' => 4, 'sort' => 1, 'name' => 'TV Series', 'description' => 'Discuss TV series', 'minclassread' => 0, 'minclasswrite' => 1, 'minclasscreate' => 1, 'forid' => 2],
            ['id' => 5, 'sort' => 2, 'name' => 'Music', 'description' => 'Music discussion', 'minclassread' => 0, 'minclasswrite' => 1, 'minclasscreate' => 1, 'forid' => 2],
        ];

        foreach ($forums as $f) {
            DB::table('forums')->updateOrInsert(['id' => $f['id']], $f);
        }
    }
}
