<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * W3-02: Convert 22 remaining enum columns to TINYINT UNSIGNED.
 *
 * Each column is converted in-place using the add-temp/copy/drop/rename
 * pattern. The int values are assigned sequentially starting from 0,
 * matching the order in the original enum definition.
 *
 * The user_preferences partition table (created in W3-01) also has
 * enum columns that mirror users columns; those are converted here too.
 */
return new class extends Migration
{
    /**
     * Columns to convert. Format: [table, column, values, default_index, nullable]
     *
     * `values` is the ordered list of original enum string values.
     * `default_index` is the 0-based index into `values` for the DEFAULT.
     */
    private const COLUMNS = [
        // users table (9 columns)
        ['users', 'status', ['pending', 'confirmed'], 0, false],
        ['users', 'privacy', ['strong', 'normal', 'low'], 1, false],
        ['users', 'fontsize', ['small', 'medium', 'large'], 1, false],
        ['users', 'acceptpms', ['yes', 'friends', 'no'], 0, false],
        ['users', 'clicktopic', ['firstpage', 'lastpage'], 0, false],
        ['users', 'gender', ['Male', 'Female', 'N/A'], 2, false],
        ['users', 'tooltip', ['minorimdb', 'medianimdb', 'off'], 2, false],
        ['users', 'timetype', ['timeadded', 'timealive'], 1, false],
        ['users', 'appendpromotion', ['highlight', 'word', 'icon', 'off'], 2, false],
        // agent_allowed_family (2 columns)
        ['agent_allowed_family', 'agent_matchtype', ['dec', 'hex'], 0, false],
        ['agent_allowed_family', 'peer_id_matchtype', ['dec', 'hex'], 0, false],
        // bitbucket (1 column)
        ['bitbucket', 'public', ['0', '1'], 0, false],
        // faq (1 column)
        ['faq', 'type', ['categ', 'item'], 0, false],
        // language (1 column)
        ['language', 'trans_state', ['up-to-date', 'outdate', 'incomplete', 'need-new', 'unavailable'], 0, false],
        // loginattempts (1 column)
        ['loginattempts', 'type', ['login', 'recover'], 0, false],
        // offers (1 column)
        ['offers', 'allowed', ['allowed', 'pending', 'denied'], 1, false],
        // offervotes (1 column)
        ['offervotes', 'vote', ['yeah', 'against'], 0, false],
        // reports (1 column)
        ['reports', 'type', ['torrent', 'user', 'offer', 'request', 'post', 'comment', 'subtitle'], 0, false],
        // shoutbox (1 column)
        ['shoutbox', 'type', ['sb'], 0, false],
        // sitelog (1 column)
        ['sitelog', 'security_level', ['normal', 'mod'], 0, false],
        // torrents (1 column)
        ['torrents', 'type', ['single', 'multi'], 0, false],
        // torrents_custom_fields (1 column)
        ['torrents_custom_fields', 'type', ['text', 'textarea', 'select', 'radio', 'checkbox', 'image'], 0, false],
        // user_preferences partition table (6 enum columns mirroring users)
        ['user_preferences', 'fontsize', ['small', 'medium', 'large'], 1, false],
        ['user_preferences', 'clicktopic', ['firstpage', 'lastpage'], 0, false],
        ['user_preferences', 'tooltip', ['minorimdb', 'medianimdb', 'off'], 2, false],
        ['user_preferences', 'timetype', ['timeadded', 'timealive'], 1, false],
        ['user_preferences', 'appendpromotion', ['highlight', 'word', 'icon', 'off'], 2, false],
        ['user_preferences', 'acceptpms', ['yes', 'friends', 'no'], 0, false],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $column, $values, $defaultIndex, $nullable]) {
            $this->convertColumn($table, $column, $values, $defaultIndex, $nullable);
        }

        // The column drop/recreate cycle removes any indexes that included
        // the converted column. Recreate the users(status, added) composite
        // index if it no longer exists (it may survive on some MySQL versions
        // or be recreated by a prior migration on fresh installs).
        $indexExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'users')
            ->where('index_name', 'users_status_added_index')
            ->exists();

        if (! $indexExists) {
            DB::statement('ALTER TABLE `users` ADD INDEX `users_status_added_index` (`status`, `added`)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::COLUMNS as [$table, $column, $values, $defaultIndex, $nullable]) {
            $this->revertColumn($table, $column, $values, $defaultIndex, $nullable);
        }
    }

    /**
     * @param  list<string>  $values
     */
    private function convertColumn(string $table, string $column, array $values, int $defaultIndex, bool $nullable): void
    {
        $tmpColumn = $column.'_enum_to_tinyint_tmp';
        $nullClause = $nullable ? 'NULL' : 'NOT NULL';
        $defaultValue = $defaultIndex;

        // Step 1: Add temp tinyint column
        DB::statement(
            "ALTER TABLE `{$table}` ADD COLUMN `{$tmpColumn}` TINYINT UNSIGNED {$nullClause} DEFAULT {$defaultValue}"
        );

        // Step 2: Copy data from enum to tinyint using CASE
        $caseSql = $this->buildCaseSql($column, $values, $tmpColumn);
        DB::statement("UPDATE `{$table}` SET `{$tmpColumn}` = {$caseSql}");

        // Step 3: Drop old enum column
        DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");

        // Step 4: Rename temp column to original name
        DB::statement(
            "ALTER TABLE `{$table}` CHANGE `{$tmpColumn}` `{$column}` TINYINT UNSIGNED {$nullClause} DEFAULT {$defaultValue}"
        );
    }

    /**
     * @param  list<string>  $values
     */
    private function revertColumn(string $table, string $column, array $values, int $defaultIndex, bool $nullable): void
    {
        $tmpColumn = $column.'_tinyint_to_enum_tmp';
        $nullClause = $nullable ? 'NULL' : 'NOT NULL';
        $defaultValue = "'".$values[$defaultIndex]."'";
        $enumList = implode(',', array_map(fn ($v) => "'".str_replace("'", "''", $v)."'", $values));

        // Step 1: Add temp enum column
        DB::statement(
            "ALTER TABLE `{$table}` ADD COLUMN `{$tmpColumn}` ENUM({$enumList}) {$nullClause} DEFAULT {$defaultValue}"
        );

        // Step 2: Copy data from tinyint to enum using CASE
        $caseParts = [];
        foreach ($values as $index => $value) {
            $escapedValue = "'".str_replace("'", "''", $value)."'";
            $caseParts[] = "WHEN {$index} THEN {$escapedValue}";
        }
        $caseSql = "CASE `{$column}` ".implode(' ', $caseParts)." ELSE {$defaultValue} END";
        DB::statement("UPDATE `{$table}` SET `{$tmpColumn}` = {$caseSql}");

        // Step 3: Drop old tinyint column
        DB::statement("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");

        // Step 4: Rename temp column to original name
        DB::statement(
            "ALTER TABLE `{$table}` CHANGE `{$tmpColumn}` `{$column}` ENUM({$enumList}) {$nullClause} DEFAULT {$defaultValue}"
        );
    }

    /**
     * Build a CASE expression that maps the old enum string values to int indices.
     *
     * @param  list<string>  $values
     */
    private function buildCaseSql(string $column, array $values, string $tmpColumn): string
    {
        $caseParts = [];
        foreach ($values as $index => $value) {
            $escapedValue = "'".str_replace("'", "''", $value)."'";
            $caseParts[] = "WHEN {$escapedValue} THEN {$index}";
        }

        return "CASE `{$column}` ".implode(' ', $caseParts)." ELSE `{$tmpColumn}` END";
    }
};
