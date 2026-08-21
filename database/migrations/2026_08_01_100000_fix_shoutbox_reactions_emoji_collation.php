<?php

use Illuminate\Database\Migrations\Migration;
use Nexus\Database\NexusDB;

class FixShoutboxReactionsEmojiCollation extends Migration
{
    public function up()
    {
        if (NexusDB::isMysql()) {
            NexusDB::statement(
                'ALTER TABLE `shoutbox_reactions` MODIFY COLUMN `reaction` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL'
            );
        } elseif (NexusDB::isPgsql()) {
            // Postgres uses byte-exact string comparison by default; no change needed.
        }
    }

    public function down()
    {
        // Reverting to a case-insensitive collation is unsafe once multiple
        // distinct emoji reactions exist, because they would be treated as
        // duplicates under utf8mb4_unicode_ci. Leave utf8mb4_bin in place;
        // it is strictly safer and has no functional downside.
    }
}
