<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Misc settings section is removed together with its two settings:
 * `misc.donation_custom` (custom BBCode text on the donate page) and
 * `misc.protected_forum` (privacy-protected forums hiding post bodies
 * from non-participants). Drop their settings rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')
            ->where('name', 'like', 'misc.%')
            ->delete();
    }

    public function down(): void
    {
        // Removed settings values cannot be restored.
    }
};
