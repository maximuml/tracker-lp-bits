<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Contracts\Repositories\TagRepositoryInterface;
use App\Enums\UserClass as UserClassEnum;
use App\Models\Category;
use App\Models\Icon;
use App\Models\Language;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Torrent;
use App\Models\TorrentTag;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Repositories\TokenRepository;
use App\Repositories\ToolRepository;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Upgrade orchestration for `app:upgrade`.
 *
 * Ports `App\Support\Install\Update` — the conditional data fixups that
 * let a pre-Laravel NexusPHP database come up to the current schema
 * (dedupe before unique indexes, taxonomy/menu/tag migrations). On a
 * current schema every block is a no-op. The upstream self-update
 * machinery (GitHub download/extract/rsync) is gone — code now arrives
 * via git pull; this service handles the post-pull housekeeping.
 */
final class UpgradeService
{
    public function __construct(
        private readonly InstallService $install,
        private readonly TagRepositoryInterface $tags,
        private readonly AttendanceRepository $attendance,
        private readonly TokenRepository $tokens,
        private readonly ToolRepository $tools,
    ) {}

    /**
     * All conditional data fixups for pre-Laravel databases, in original
     * order. Idempotent: every block checks schema/data before acting.
     *
     * @param  callable(string):void|null  $log
     */
    public function runLegacyFixups(?callable $log = null): void
    {
        // @since 1.10, must run first
        $this->install->migrate('database/migrations/2025_10_05_030400_create_activity_log_table.php');

        $redis = Redis::connection()->client();

        // @since 1.7.13
        foreach (['adminpanel', 'modpanel', 'sysoppanel'] as $table) {
            $columnInfo = $this->columnInfo($table, 'id');
            if ($columnInfo !== null && ($columnInfo['type_name'] === 'tinyint' || ! ($columnInfo['auto_increment'] ?? false))) {
                DB::statement("alter table $table modify id int(11) unsigned not null AUTO_INCREMENT");
            }
        }

        // custom field menu
        $this->addMenu('adminpanel', [
            ['name' => 'Custom Field Manage', 'url' => 'fields.php', 'info' => 'Manage custom fields'],
        ], $log);

        // since beta8
        if (! Schema::hasColumn('categories', 'icon_id')) {
            $this->log($log, '[INIT CATEGORY ICON_ID]');
            $this->install->migrate('database/migrations/2022_03_08_040415_add_icon_id_to_categories_table.php');
            $icon = Icon::query()->orderBy('id', 'asc')->first();
            if ($icon) {
                Category::query()->where('icon_id', 0)->update(['icon_id' => $icon->id]);
            }
        }
        // fix base url, since beta8
        if (Schema::hasTable('settings')) {
            $settingBasic = SiteConfig::current()->basic->toArray();
            if (isset($settingBasic['BASEURL']) && Str::startsWith($settingBasic['BASEURL'], 'localhost')) {
                $this->log($log, '[RESET CONFIG basic.BASEURL]');
                Setting::query()->where('name', 'basic.BASEURL')->update(['value' => '']);
            }
            if (isset($settingBasic['announce_url']) && Str::startsWith($settingBasic['announce_url'], 'localhost')) {
                $this->log($log, '[RESET CONFIG basic.announce_url]');
                Setting::query()->where('name', 'basic.announce_url')->update(['value' => '']);
            }
        }

        // torrent support sticky second level
        $columnInfo = $this->columnInfo('torrents', 'pos_state');
        $this->log($log, '[TORRENT POS_STATE], column info: '.json_encode($columnInfo));
        if ($columnInfo !== null && $columnInfo['type_name'] === 'enum') {
            $sql = "alter table torrents modify `pos_state` varchar(32) NOT NULL DEFAULT 'normal'";
            $this->log($log, "[ALTER TORRENT POS_STATE TYPE TO VARCHAR], $sql");
            DB::statement($sql);
        }

        // @since 1.6.0-beta9 — attendance change, do migrate
        if (! Schema::hasTable('attendance')) {
            // no table yet, no need to migrate
            $this->install->migrate('database/migrations/2021_06_08_113437_create_attendance_table.php');
        }
        if (! Schema::hasColumn('attendance', 'total_days')) {
            $this->install->migrate('database/migrations/2021_06_13_215440_add_total_days_to_attendance_table.php');
            $count = $this->attendance->migrateAttendance();
            $this->log($log, "[MIGRATE_ATTENDANCE] $count");
        }

        // @since 1.6.0-beta13 — add seed points to user
        if (! Schema::hasColumn('users', 'seed_points')) {
            $this->install->migrate('database/migrations/2021_06_24_013107_add_seed_points_to_users_table.php');
            // initial seed points = 0
            $this->log($log, '[INIT SEED POINTS]');
        }

        // @since 1.6.0-beta14 — add id to agent_allowed_exception
        if (! Schema::hasColumn('agent_allowed_exception', 'id')) {
            $this->install->migrate('database/migrations/2022_02_25_021356_add_id_to_agent_allowed_exception_table.php');
            $this->log($log, '[ADD_ID_TO_AGENT_ALLOWED_EXCEPTION]');
        }

        // @since 1.6.0 — init tag
        if (! Schema::hasTable('tags')) {
            $this->install->migrate('database/migrations/2022_03_07_012545_create_tags_table.php');
            $this->initTag();
            $this->log($log, '[INIT_TAG]');
        }

        // @since 1.6.3 — add usersearch.php and unco.php
        $this->addMenu('modpanel', [
            ['name' => 'Search user', 'url' => 'usersearch.php', 'info' => 'Search user'],
            ['name' => 'Confirm user', 'url' => 'unco.php', 'info' => 'Confirm user to complete registration'],
        ], $log);

        // @since 1.7.0 — add attendance_card to users
        if (! Schema::hasColumn('users', 'attendance_card')) {
            $this->install->migrate('database/migrations/2022_04_02_163930_create_attendance_logs_table.php');
            $this->install->migrate('database/migrations/2022_04_03_041642_add_attendance_card_to_users_table.php');
            $count = $this->attendance->migrateAttendanceLogs();
            $this->log($log, "[ADD_ATTENDANCE_CARD_TO_USERS], migrateAttendanceLogs: $count");
        }

        // @since 1.7.12
        $this->addMenu('sysoppanel', [
            ['name' => 'Add Bonus/Attend card/Invite/upload', 'url' => 'increment-bulk.php', 'info' => 'Add Bonus/Attend card/Invite/upload to certain classes'],
        ], $log);
        $this->removeMenu(['amountupload.php', 'amountattendancecard.php', 'amountbonus.php', 'deletedisabled.php'], $log);

        // @since 1.7.19
        $this->removeMenu(['freeleech.php'], $log);
        Cache::forgetWithLocales('nexus_rss');

        // @since 1.7.24
        if (! Schema::hasColumn('searchbox', 'extra')) {
            $this->install->migrate('database/migrations/2022_09_02_031539_add_extra_to_searchbox_table.php');
            SearchBox::query()->update(['extra' => [
                SearchBox::EXTRA_DISPLAY_COVER_ON_TORRENT_LIST => 1,
            ]]);
        }

        // @since 1.8.0
        $shouldMigrateSearchBox = false;
        if (! Schema::hasColumn('searchbox', 'section_name')) {
            $shouldMigrateSearchBox = true;
            $searchBoxLog = 'no section_name field';
        } else {
            $columnInfo = $this->columnInfo('searchbox', 'section_name');
            $searchBoxLog = 'has section_name, searchbox.section DATA_TYPE: '.($columnInfo['type_name'] ?? '?');
            if (($columnInfo['type_name'] ?? null) !== 'json') {
                $searchBoxLog .= ', not json';
                $shouldMigrateSearchBox = true;
            }
        }
        $this->log($log, "$searchBoxLog, shouldMigrateSearchBox: ".var_export($shouldMigrateSearchBox, true));
        if ($shouldMigrateSearchBox) {
            $this->install->migrate('database/migrations/2021_06_08_113437_create_searchbox_table.php');
            $this->install->migrate('database/migrations/2022_03_08_041951_add_custom_fields_to_searchbox_table.php');
            $this->install->migrate('database/migrations/2022_09_02_031539_add_extra_to_searchbox_table.php');
            $this->install->migrate('database/migrations/2022_09_05_230532_add_mode_to_section_related.php');
            $this->install->migrate('database/migrations/2022_09_06_004318_add_section_name_to_searchbox_table.php');
            $this->install->migrate('database/migrations/2022_09_06_030324_change_searchbox_field_extra_to_json.php');
            $this->install->migrateSearchBoxModeRelated($log);
            $this->log($log, '[MIGRATE_TAXONOMY_TO_MODE_RELATED]');
        }
        $this->removeMenu(['catmanage.php'], $log);

        if (! Schema::hasColumn('users', 'seed_points_updated_at')) {
            $this->install->migrate('database/migrations/2022_11_23_042152_add_seed_points_seed_times_update_time_to_users_table.php');
            foreach (User::$notificationOptions as $option) {
                $sql = "update users set notifs = concat(notifs, '[$option]') where instr(notifs, '[$option]') = 0";
                DB::statement($sql);
            }
        }

        if (! $this->isSnatchedTableTorrentUserUnique()) {
            $this->tools->removeDuplicateSnatch();
            $this->install->migrate('database/migrations/2023_03_29_021950_handle_snatched_user_torrent_unique.php');
            $this->log($log, 'removeDuplicateSnatch and migrate 2023_03_29_021950_handle_snatched_user_torrent_unique');
        }

        if (! $this->peersHasUniqueTorrentPeerUser()) {
            $this->tools->removeDuplicatePeer();
            $this->install->migrate('database/migrations/2023_04_01_005409_add_unique_torrent_peer_user_to_peers_table.php');
            $this->log($log, 'removeDuplicatePeer and migrate 2023_04_01_005409_add_unique_torrent_peer_user_to_peers_table');
        }

        // @since 1.8.3
        $hasTableSetting = Schema::hasTable('settings');
        if ($hasTableSetting) {
            $updateSettings = [];
            if (SiteConfig::current()->system->meilisearchEnabled()) {
                $updateSettings['enabled'] = 'yes';
            }
            if (SiteConfig::current()->system->meilisearchSearchDescription()) {
                $updateSettings['search_description'] = 'yes';
            }
            if ($updateSettings !== []) {
                $this->install->saveSettings(['meilisearch' => $updateSettings], $log);
            }
        }

        // @since 1.8.10
        if ($hasTableSetting) {
            $staffLeaderId = User::query()->where('class', UserClassEnum::STAFFLEADER->value)->value('id');
            if ($staffLeaderId !== null) {
                Setting::query()->firstOrCreate(
                    ['name' => 'system.alarm_email_receiver'],
                    ['value' => (string) $staffLeaderId]
                );
            }
        }

        // @since 1.9.0
        if (! Schema::hasTable('torrent_extras')) {
            $this->install->migrate('database/migrations/2025_01_08_133552_create_torrent_extra_table.php');
            Artisan::call('upgrade:migrate_torrents_table_text_column');
            Language::updateTransStatus();
            $this->addSetting('main.complain_enabled', 'yes');
            $this->addSetting('image_hosting.driver', 'local');
            $this->addSetting('permission.user_token_allowed', (string) json_encode($this->tokens->listUserTokenPermissions(false)));
        }
        if (! $redis->exists(Setting::USER_TOKEN_PERMISSION_ALLOWED_CACHE_KRY)) {
            Setting::updateUserTokenPermissionAllowedCache($this->tokens->listUserTokenPermissions(false));
        }

        // @since 1.9.5
        if (! Schema::hasColumn('snatched', 'hit_and_run_id')) {
            $this->install->migrate('database/migrations/2025_06_09_222012_add_hr_and_buy_id_to_snatched_table.php');
            Artisan::call('upgrade:migrate_snatched_hr_id');
            Artisan::call('upgrade:migrate_snatched_buy_log_id');
        }
        if (! Schema::hasTable('tracker_urls')) {
            $this->install->migrate('database/migrations/2025_06_19_194137_create_tracker_urls_table.php');
            $this->install->initTrackerUrl('update', $log);
        }
    }

    /**
     * Post-migration data work: port `Update::runExtraMigrate()`.
     * Moves legacy `torrents.tags` into the tag pivot and drops the column.
     *
     * @param  callable(string):void|null  $log
     */
    public function runExtraMigrate(?callable $log = null): void
    {
        if (Schema::hasColumn('torrents', 'tags')) {
            if (Torrent::query()->where('tags', '>', 0)->count() > 0 && TorrentTag::query()->count() === 0) {
                $this->log($log, '[MIGRATE_TORRENT_TAG]...');
                $this->tags->migrateTorrentTag();
                $this->log($log, '[MIGRATE_TORRENT_TAG] done!');
            }
            $sql = 'alter table torrents drop column tags';
            DB::statement($sql);
            $this->log($log, $sql);
        } else {
            $this->log($log, 'torrents table does not has column: tags');
        }

        Cache::clearSettings();
    }

    /**
     * @param  list<array{name: string, url: string, info: string}>  $menus
     * @param  callable(string):void|null  $log
     */
    private function addMenu(string $table, array $menus, ?callable $log): void
    {
        foreach ($menus as $menu) {
            if (DB::table($table)->where('url', $menu['url'])->count() === 0) {
                $id = DB::table($table)->insertGetId($menu);
                $this->log($log, '[ADD MENU] insert: '.json_encode($menu)." to table: $table, id: $id");
            }
        }
    }

    /**
     * @param  list<string>  $menus
     * @param  list<string>  $tables
     * @param  callable(string):void|null  $log
     */
    private function removeMenu(array $menus, ?callable $log, array $tables = ['sysoppanel', 'adminpanel', 'modpanel']): void
    {
        $this->log($log, '[REMOVE MENU]: '.json_encode($menus));
        if ($menus === []) {
            return;
        }
        foreach ($tables as $table) {
            DB::table($table)->whereIn('url', $menus)->delete();
        }
    }

    private function initTag(): void
    {
        $priority = count(Tag::DEFAULTS);
        $dateTimeStringNow = date('Y-m-d H:i:s');
        foreach (Tag::DEFAULTS as $value) {
            Tag::query()->firstOrCreate(
                ['name' => $value['name']],
                [
                    'priority' => $priority,
                    'color' => $value['color'],
                    'created_at' => $dateTimeStringNow,
                    'updated_at' => $dateTimeStringNow,
                ]
            );
            $priority--;
        }
    }

    private function addSetting(string $name, string $value): void
    {
        $now = Carbon::now()->toDateTimeString();
        Setting::query()->firstOrCreate(
            ['name' => $name],
            ['value' => $value, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    private function isSnatchedTableTorrentUserUnique(): bool
    {
        foreach (Schema::getIndexes('snatched') as $index) {
            if (! empty($index['unique'])
                && in_array('torrentid', $index['columns'], true)
                && in_array('userid', $index['columns'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The unique (torrent, peer_id, userid) index the 2023_04_01 migration
     * adds. Checked by columns, not name: Laravel auto-names it
     * `peers_torrent_peer_id_userid_unique`, so the legacy name check
     * (`unique_torrent_peer_user`) never matched and re-ran the dedupe on
     * every upgrade.
     */
    private function peersHasUniqueTorrentPeerUser(): bool
    {
        foreach (Schema::getIndexes('peers') as $index) {
            if (! empty($index['unique'])
                && in_array('torrent', $index['columns'], true)
                && in_array('peer_id', $index['columns'], true)
                && in_array('userid', $index['columns'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null column metadata (Schema::getColumns entry)
     */
    private function columnInfo(string $table, string $column): ?array
    {
        foreach (Schema::getColumns($table) as $col) {
            if ($col['name'] === $column) {
                return $col;
            }
        }

        return null;
    }

    /**
     * @param  callable(string):void|null  $log
     */
    private function log(?callable $log, string $message): void
    {
        if ($log !== null) {
            $log($message);
        }
    }
}
