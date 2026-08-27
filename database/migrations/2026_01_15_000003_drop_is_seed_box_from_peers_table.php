<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peers', function (Blueprint $table) {
            if (Schema::hasColumn('peers', 'is_seed_box')) {
                $table->dropColumn('is_seed_box');
            }
        });
    }

    public function down(): void
    {
        Schema::table('peers', function (Blueprint $table) {
            if (! Schema::hasColumn('peers', 'is_seed_box')) {
                $table->tinyInteger('is_seed_box')->default(0);
            }
        });
    }
};
