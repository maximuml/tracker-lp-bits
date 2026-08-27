<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('plugins');
    }

    public function down(): void
    {
        // Recreating the original plugins table is out of scope.
        // The plugin system has been fully removed from the application.
    }
};
