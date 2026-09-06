<?php

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * W3-01: Backfill the three user partition tables from the users table.
 *
 * Processes in batches of 1000 to avoid memory issues. Uses
 * INSERT ... ON DUPLICATE KEY UPDATE so the migration is idempotent
 * and can be re-run safely.
 */
return new class extends Migration
{
    private const BATCH_SIZE = 1000;

    /** @var list<string> */
    private const PREFERENCE_COLUMNS = [
        'stylesheet', 'caticon', 'fontsize', 'torrentsperpage', 'topicsperpage',
        'postsperpage', 'clicktopic', 'tooltip', 'timetype', 'appendpromotion',
        'appendnew', 'appendpicked', 'appendsticky', 'avatars', 'bmicon',
        'commentpm', 'deletepms', 'dlicon', 'forumpost', 'savepms',
        'showclienterror', 'showcomment', 'showcomnum', 'showdescription',
        'showimdb', 'showlastcom', 'showlastpost', 'shownfo', 'showsmalldescr',
        'signatures', 'acceptpms', 'notifs', 'lang', 'sbnum', 'sbrefresh',
        'showdlnotice', 'clientselect', 'info', 'support', 'stafffor',
        'supportfor', 'pickfor', 'supportlang', 'page', 'signature',
    ];

    /** @var list<string> */
    private const ACTIVITY_COLUMNS = [
        'last_login', 'last_access', 'last_home', 'last_offer', 'forum_access',
        'last_staffmsg', 'last_pm', 'last_comment', 'last_post', 'last_browse',
        'last_music', 'last_catchup', 'last_announce_at',
    ];

    /** @var list<string> */
    private const SEED_STATS_COLUMNS = [
        'seed_points', 'seed_points_per_hour', 'seed_bonus_per_hour',
        'seed_points_updated_at', 'seed_time_updated_at',
        'seeding_torrent_count', 'seeding_torrent_size',
        'attendance_card', 'offer_allowed_count',
    ];

    public function up(): void
    {
        $connection = DB::connection();
        $total = $connection->table('users')->count();
        $batches = (int) ceil($total / self::BATCH_SIZE);

        for ($i = 0; $i < $batches; $i++) {
            $offset = $i * self::BATCH_SIZE;
            $this->backfillPreferences($connection, $offset);
            $this->backfillActivity($connection, $offset);
            $this->backfillSeedStats($connection, $offset);
        }
    }

    public function down(): void
    {
        DB::connection()->table('user_preferences')->truncate();
        DB::connection()->table('user_activity')->truncate();
        DB::connection()->table('user_seed_stats')->truncate();
    }

    /**
     * @param  ConnectionInterface  $connection
     */
    private function backfillPreferences($connection, int $offset): void
    {
        $rows = $connection->table('users')
            ->select(array_merge(['id'], self::PREFERENCE_COLUMNS))
            ->skip($offset)
            ->take(self::BATCH_SIZE)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $insertRows = [];
        foreach ($rows as $row) {
            $data = ['user_id' => $row->id];
            foreach (self::PREFERENCE_COLUMNS as $col) {
                $data[$col] = $row->{$col};
            }
            $insertRows[] = $data;
        }

        $this->upsert($connection, 'user_preferences', $insertRows, self::PREFERENCE_COLUMNS);
    }

    /**
     * @param  ConnectionInterface  $connection
     */
    private function backfillActivity($connection, int $offset): void
    {
        $rows = $connection->table('users')
            ->select(array_merge(['id'], self::ACTIVITY_COLUMNS))
            ->skip($offset)
            ->take(self::BATCH_SIZE)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $insertRows = [];
        foreach ($rows as $row) {
            $data = ['user_id' => $row->id];
            foreach (self::ACTIVITY_COLUMNS as $col) {
                $data[$col] = $row->{$col};
            }
            $insertRows[] = $data;
        }

        $this->upsert($connection, 'user_activity', $insertRows, self::ACTIVITY_COLUMNS);
    }

    /**
     * @param  ConnectionInterface  $connection
     */
    private function backfillSeedStats($connection, int $offset): void
    {
        $rows = $connection->table('users')
            ->select(array_merge(['id'], self::SEED_STATS_COLUMNS))
            ->skip($offset)
            ->take(self::BATCH_SIZE)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $insertRows = [];
        foreach ($rows as $row) {
            $data = ['user_id' => $row->id];
            foreach (self::SEED_STATS_COLUMNS as $col) {
                $data[$col] = $row->{$col};
            }
            $insertRows[] = $data;
        }

        $this->upsert($connection, 'user_seed_stats', $insertRows, self::SEED_STATS_COLUMNS);
    }

    /**
     * @param  ConnectionInterface  $connection
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $updateColumns
     */
    private function upsert($connection, string $table, array $rows, array $updateColumns): void
    {
        if ($rows === []) {
            return;
        }

        $connection->table($table)->upsert($rows, ['user_id'], $updateColumns);
    }
};
