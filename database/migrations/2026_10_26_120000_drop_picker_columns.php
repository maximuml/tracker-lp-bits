<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function ($table) {
            $table->dropColumn(['picker', 'pickfor']);
        });

        if (Schema::hasTable('user_preferences') && Schema::hasColumn('user_preferences', 'pickfor')) {
            Schema::table('user_preferences', function ($table) {
                $table->dropColumn('pickfor');
            });
        }
    }

    public function down(): void
    {
        // Re-creating the picker role columns is not practical.
    }
};
