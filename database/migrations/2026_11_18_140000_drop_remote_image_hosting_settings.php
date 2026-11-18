<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Chevereto and Lsky image-hosting drivers are removed — only the
 * local attachment driver ships now. Drop their settings rows; a stale
 * `image_hosting.driver` value pointing at a removed driver is pinned
 * back to `local` so uploads keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('name', 'like', 'image_hosting\_chevereto.%')
            ->orWhere('name', 'like', 'image_hosting\_lsky.%')
            ->delete();

        DB::table('settings')
            ->where('name', 'image_hosting.driver')
            ->whereNotIn('value', ['local'])
            ->update(['value' => 'local']);
    }

    public function down(): void
    {
        // Removed settings values cannot be restored.
    }
};
