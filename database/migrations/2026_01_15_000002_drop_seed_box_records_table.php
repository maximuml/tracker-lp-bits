<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('seed_box_records');
    }

    public function down(): void
    {
        // Recreating the original seed_box_records table is out of scope.
        // The seedbox feature has been fully removed from the application.
    }
};
