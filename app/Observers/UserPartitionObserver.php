<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Models\UserActivity;
use App\Models\UserPreference;
use App\Models\UserSeedStats;

/**
 * W3-01: Dual-write observer for user vertical partitioning.
 *
 * Keeps user_preferences, user_activity, and user_seed_stats in sync
 * with the users table during phase 1. All reads still go to users;
 * this observer only ensures the partition tables receive the same
 * writes so that phase 2 can switch reads over safely.
 */
class UserPartitionObserver
{
    /** @var array<string, list<string>> */
    private const PREFERENCE_COLUMNS = [
        'user_preferences' => [
            'stylesheet', 'caticon', 'fontsize', 'torrentsperpage', 'topicsperpage',
            'postsperpage', 'clicktopic', 'tooltip', 'timetype', 'appendpromotion',
            'appendnew', 'appendpicked', 'appendsticky', 'avatars', 'bmicon',
            'commentpm', 'deletepms', 'dlicon', 'forumpost', 'savepms',
            'showclienterror', 'showcomment', 'showcomnum', 'showdescription',
            'showimdb', 'showlastcom', 'showlastpost', 'shownfo', 'showsmalldescr',
            'signatures', 'acceptpms', 'notifs', 'lang', 'sbnum', 'sbrefresh',
            'showdlnotice', 'clientselect', 'info', 'support', 'stafffor',
            'supportfor', 'pickfor', 'supportlang', 'page', 'signature',
        ],
        'user_activity' => [
            'last_login', 'last_access', 'last_home', 'last_offer', 'forum_access',
            'last_staffmsg', 'last_pm', 'last_comment', 'last_post', 'last_browse',
            'last_music', 'last_catchup', 'last_announce_at',
        ],
        'user_seed_stats' => [
            'seed_points', 'seed_points_per_hour', 'seed_bonus_per_hour',
            'seed_points_updated_at', 'seed_time_updated_at',
            'seeding_torrent_count', 'seeding_torrent_size',
            'attendance_card', 'offer_allowed_count',
        ],
    ];

    public function created(User $user): void
    {
        $attributes = $user->getAttributes();

        $preferenceData = ['user_id' => $user->id];
        foreach (self::PREFERENCE_COLUMNS['user_preferences'] as $column) {
            if (array_key_exists($column, $attributes) && $attributes[$column] !== null) {
                $preferenceData[$column] = $attributes[$column];
            }
        }
        UserPreference::firstOrCreate(['user_id' => $user->id], $preferenceData);

        $activityData = ['user_id' => $user->id];
        foreach (self::PREFERENCE_COLUMNS['user_activity'] as $column) {
            if (array_key_exists($column, $attributes) && $attributes[$column] !== null) {
                $activityData[$column] = $attributes[$column];
            }
        }
        UserActivity::firstOrCreate(['user_id' => $user->id], $activityData);

        $seedStatsData = ['user_id' => $user->id];
        foreach (self::PREFERENCE_COLUMNS['user_seed_stats'] as $column) {
            if (array_key_exists($column, $attributes) && $attributes[$column] !== null) {
                $seedStatsData[$column] = $attributes[$column];
            }
        }
        UserSeedStats::firstOrCreate(['user_id' => $user->id], $seedStatsData);
    }

    public function updated(User $user): void
    {
        $dirty = $user->getDirty();
        if ($dirty === []) {
            return;
        }

        $partitions = User::$partitionedColumns;

        $affectedTables = [];
        foreach (array_keys($dirty) as $column) {
            if (isset($partitions[$column])) {
                $affectedTables[$partitions[$column]][] = $column;
            }
        }

        foreach ($affectedTables as $table => $columns) {
            $this->updatePartition($user, $table, $columns);
        }
    }

    public function deleted(User $user): void
    {
        // FK CASCADE handles deletion, but clean up explicitly for safety.
        UserPreference::where('user_id', $user->id)->delete();
        UserActivity::where('user_id', $user->id)->delete();
        UserSeedStats::where('user_id', $user->id)->delete();
    }

    /**
     * @param  list<string>  $columns
     */
    private function updatePartition(User $user, string $table, array $columns): void
    {
        $data = [];
        foreach ($columns as $column) {
            $data[$column] = $user->getAttribute($column);
        }

        match ($table) {
            'user_preferences' => UserPreference::updateOrCreate(['user_id' => $user->id], $data),
            'user_activity' => UserActivity::updateOrCreate(['user_id' => $user->id], $data),
            'user_seed_stats' => UserSeedStats::updateOrCreate(['user_id' => $user->id], $data),
            default => null,
        };
    }
}
