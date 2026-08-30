<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert remaining enum('yes','no') columns missed by the main migration.
     * messages.saved and topics.sticky were not in the audit list but are enum('yes','no').
     */
    public function up(): void
    {
        // messages.saved — default 'no'
        DB::statement('ALTER TABLE `messages` ADD COLUMN `saved_bool_tmp` TINYINT(1) NOT NULL DEFAULT 0');
        DB::statement("UPDATE `messages` SET `saved_bool_tmp` = IF(`saved` = 'yes', 1, 0)");
        DB::statement('ALTER TABLE `messages` DROP COLUMN `saved`');
        DB::statement('ALTER TABLE `messages` CHANGE `saved_bool_tmp` `saved` TINYINT(1) NOT NULL DEFAULT 0');

        // topics.sticky — default 'no'
        DB::statement('ALTER TABLE `topics` ADD COLUMN `sticky_bool_tmp` TINYINT(1) NOT NULL DEFAULT 0');
        DB::statement("UPDATE `topics` SET `sticky_bool_tmp` = IF(`sticky` = 'yes', 1, 0)");
        DB::statement('ALTER TABLE `topics` DROP COLUMN `sticky`');
        DB::statement('ALTER TABLE `topics` CHANGE `sticky_bool_tmp` `sticky` TINYINT(1) NOT NULL DEFAULT 0');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `messages` ADD COLUMN `saved_enum_tmp` ENUM('no','yes') NOT NULL DEFAULT 'no'");
        DB::statement("UPDATE `messages` SET `saved_enum_tmp` = IF(`saved` = 1, 'yes', 'no')");
        DB::statement('ALTER TABLE `messages` DROP COLUMN `saved`');
        DB::statement("ALTER TABLE `messages` CHANGE `saved_enum_tmp` `saved` ENUM('no','yes') NOT NULL DEFAULT 'no'");

        DB::statement("ALTER TABLE `topics` ADD COLUMN `sticky_enum_tmp` ENUM('no','yes') NOT NULL DEFAULT 'no'");
        DB::statement("UPDATE `topics` SET `sticky_enum_tmp` = IF(`sticky` = 1, 'yes', 'no')");
        DB::statement('ALTER TABLE `topics` DROP COLUMN `sticky`');
        DB::statement("ALTER TABLE `topics` CHANGE `sticky_enum_tmp` `sticky` ENUM('no','yes') NOT NULL DEFAULT 'no'");
    }
};
