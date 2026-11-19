<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rework the torrent category set to the music-tracker lineup:
 *
 *   404 Documentaries \ Misc   (absorbs old 402/403/405/409 rows' torrents)
 *   406 Music Videos           (unchanged)
 *   407 Audio Professional     (was Sports; absorbs old 408 HQ Audio)
 *   408 Pro Shot Video         (was HQ Audio; absorbs old 401 Movies)
 *   409 Video Audience Shot    (was Misc)
 *   410 Audio Audience Shot    (new)
 *
 * Rows 401, 402, 403, 405 are deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['torrents', 'offers'] as $table) {
            DB::table($table)->where('category', 408)->update(['category' => 407]);
            DB::table($table)->where('category', 401)->update(['category' => 408]);
            DB::table($table)->whereIn('category', [402, 403, 405, 409])->update(['category' => 404]);
        }

        DB::table('categories')->whereIn('id', [401, 402, 403, 405])->delete();

        DB::table('categories')->where('id', 404)->update([
            'name' => 'Documentaries \\ Misc', 'class_name' => 'c_doc', 'sort_index' => 5,
        ]);
        DB::table('categories')->where('id', 406)->update([
            'name' => 'Music Videos', 'class_name' => 'c_mv', 'sort_index' => 4,
        ]);
        DB::table('categories')->where('id', 407)->update([
            'name' => 'Audio Professional', 'class_name' => 'c_hqaudio', 'sort_index' => 3,
        ]);
        DB::table('categories')->where('id', 408)->update([
            'name' => 'Pro Shot Video', 'class_name' => 'c_movies', 'sort_index' => 2,
        ]);
        DB::table('categories')->where('id', 409)->update([
            'name' => 'Video Audience Shot', 'class_name' => 'c_tvshows', 'sort_index' => 1,
        ]);
        DB::table('categories')->insert([
            'id' => 410,
            'mode' => 4,
            'class_name' => 'c_misc',
            'name' => 'Audio Audience Shot',
            'image' => 'catsprites.png',
            'sort_index' => 0,
            'icon_id' => 1,
        ]);
    }

    public function down(): void
    {
        // Old category names/assignments cannot be restored automatically.
    }
};
