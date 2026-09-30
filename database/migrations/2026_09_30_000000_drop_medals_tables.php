<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_medals');
        Schema::dropIfExists('medals');

        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('name', 'system.maximum_number_of_medals_can_be_worn')->delete();
        }
    }

    public function down(): void
    {
        // Re-creating the medal feature tables is not practical.
    }
};
