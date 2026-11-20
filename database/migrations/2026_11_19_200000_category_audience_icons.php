<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Point the three categories that lacked a fitting sprite at the new
 * catsprites cells added alongside this migration:
 *   408 Pro Shot Video       -> c_proshot  (camera tile)
 *   409 Video Audience Shot  -> c_vidaud   (phone-over-crowd tile)
 *   410 Audio Audience Shot  -> c_audaud   (mic-over-crowd tile)
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('categories')->where('id', 408)->update(['class_name' => 'c_proshot']);
        DB::table('categories')->where('id', 409)->update(['class_name' => 'c_vidaud']);
        DB::table('categories')->where('id', 410)->update(['class_name' => 'c_audaud']);
    }

    public function down(): void
    {
        DB::table('categories')->where('id', 408)->update(['class_name' => 'c_movies']);
        DB::table('categories')->where('id', 409)->update(['class_name' => 'c_tvshows']);
        DB::table('categories')->where('id', 410)->update(['class_name' => 'c_misc']);
    }
};
