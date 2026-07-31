<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('agent_allowed_family')
            ->where('family', 'BiglyBT 3.x')
            ->update([
                'agent_pattern' => '/^BiglyBT\/3\\.([0-9])\\.([0-9])\\.([0-9])/',
            ]);
    }

    public function down(): void
    {
        DB::table('agent_allowed_family')
            ->where('family', 'BiglyBT 3.x')
            ->update([
                'agent_pattern' => '/^BiglyBT\\ /3\\.([0-9])\\.([0-9])\\.([0-9])/',
            ]);
    }
};
