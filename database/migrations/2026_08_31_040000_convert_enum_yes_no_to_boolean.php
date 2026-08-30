<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Columns to convert from enum('yes','no') to tinyint(1).
     *
     * Format: [table, column, nullable, default_is_yes]
     */
    private const COLUMNS = [
        // agent_allowed_family
        ['agent_allowed_family', 'allowhttps', false, false],
        ['agent_allowed_family', 'exception', false, false],
        // caticons
        ['caticons', 'multilang', false, false],
        ['caticons', 'secondicon', false, false],
        // comments
        ['comments', 'anonymous', false, false],
        // loginattempts
        ['loginattempts', 'banned', false, false],
        // messages
        ['messages', 'unread', false, true],
        // news
        ['news', 'notify', false, false],
        // peers
        ['peers', 'connectable', false, true],
        ['peers', 'seeder', false, false],
        // resreq
        ['resreq', 'chosen', false, false],
        // settings
        ['settings', 'autoload', false, true],
        // snatched
        ['snatched', 'finished', false, false],
        // topics
        ['topics', 'locked', false, false],
        // torrents
        ['torrents', 'anonymous', false, false],
        ['torrents', 'banned', false, false],
        ['torrents', 'visible', false, true],
        // users
        ['users', 'appendnew', true, true],
        ['users', 'appendpicked', true, true],
        ['users', 'appendsticky', true, true],
        ['users', 'avatars', false, true],
        ['users', 'bmicon', true, true],
        ['users', 'commentpm', false, true],
        ['users', 'deletepms', false, true],
        ['users', 'dlicon', true, true],
        ['users', 'donor', false, false],
        ['users', 'downloadpos', false, true],
        ['users', 'enabled', false, true],
        ['users', 'forumpost', false, true],
        ['users', 'leechwarn', false, false],
        ['users', 'noad', false, false],
        ['users', 'parked', false, false],
        ['users', 'picker', false, false],
        ['users', 'savepms', false, false],
        ['users', 'showclienterror', false, false],
        ['users', 'showcomment', true, true],
        ['users', 'showcomnum', true, true],
        ['users', 'showdescription', true, true],
        ['users', 'showimdb', true, true],
        ['users', 'showlastcom', true, false],
        ['users', 'showlastpost', false, false],
        ['users', 'shownfo', true, true],
        ['users', 'showsmalldescr', false, true],
        ['users', 'signatures', false, true],
        ['users', 'support', false, false],
        ['users', 'uploadpos', false, true],
        ['users', 'vip_added', false, false],
        ['users', 'warned', false, false],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $column, $nullable, $defaultYes]) {
            $default = $defaultYes ? 1 : 0;
            $nullClause = $nullable ? 'NULL' : 'NOT NULL';
            $tmpColumn = $column.'_enum_to_bool_tmp';

            // Step 1: Add temp boolean column
            DB::statement(
                "ALTER TABLE `{$table}` ADD COLUMN `{$tmpColumn}` TINYINT(1) {$nullClause} DEFAULT {$default}"
            );

            // Step 2: Copy data from enum to boolean
            DB::statement(
                "UPDATE `{$table}` SET `{$tmpColumn}` = IF(`{$column}` = 'yes', 1, 0)"
            );

            // Step 3: Drop old enum column
            DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");

            // Step 4: Rename temp column to original name
            DB::statement(
                "ALTER TABLE `{$table}` CHANGE `{$tmpColumn}` `{$column}` TINYINT(1) {$nullClause} DEFAULT {$default}"
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::COLUMNS as [$table, $column, $nullable, $defaultYes]) {
            $default = $defaultYes ? "'yes'" : "'no'";
            $nullClause = $nullable ? 'NULL' : 'NOT NULL';
            $tmpColumn = $column.'_bool_to_enum_tmp';

            // Step 1: Add temp enum column
            DB::statement(
                "ALTER TABLE `{$table}` ADD COLUMN `{$tmpColumn}` ENUM('yes','no') {$nullClause} DEFAULT {$default}"
            );

            // Step 2: Copy data from boolean to enum
            DB::statement(
                "UPDATE `{$table}` SET `{$tmpColumn}` = IF(`{$column}` = 1, 'yes', 'no')"
            );

            // Step 3: Drop old boolean column
            DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");

            // Step 4: Rename temp column to original name
            DB::statement(
                "ALTER TABLE `{$table}` CHANGE `{$tmpColumn}` `{$column}` ENUM('yes','no') {$nullClause} DEFAULT {$default}"
            );
        }
    }
};
