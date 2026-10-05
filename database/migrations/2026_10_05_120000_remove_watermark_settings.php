<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('name', 'like', 'attachment.watermark%')->delete();
        }
    }

    public function down(): void
    {
        // Watermark feature removed; deleted settings are not restored.
    }
};
