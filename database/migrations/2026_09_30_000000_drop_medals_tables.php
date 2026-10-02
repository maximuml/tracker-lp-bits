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

            $allowed = DB::table('settings')->where('name', 'permission.user_token_allowed')->value('value');
            if (is_string($allowed) && $allowed !== '') {
                $list = json_decode($allowed, true);
                if (is_array($list)) {
                    $list = array_values(array_filter(
                        $list,
                        static fn (mixed $p): bool => is_string($p)
                            && ! str_starts_with($p, 'medal:')
                            && ! str_starts_with($p, 'user_medal:'),
                    ));
                    DB::table('settings')
                        ->where('name', 'permission.user_token_allowed')
                        ->update(['value' => json_encode($list)]);
                }
            }
        }
    }

    public function down(): void
    {
        // Re-creating the medal feature tables is not practical.
    }
};
