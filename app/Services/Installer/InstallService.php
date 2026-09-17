<?php

declare(strict_types=1);

namespace App\Services\Installer;

use App\Contracts\Repositories\SearchBoxRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\UserClass as UserClassEnum;
use App\Models\TrackerUrl;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Env;
use App\Support\Path;
use App\Support\Settings;
use App\Support\Url;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Fresh-install orchestration for `app:install`.
 *
 * Ports the DB/settings/symlink/admin work of the legacy web installer
 * (`app/Support/Install/Install.php`) onto plain `DB::`/`Schema::` —
 * no session wizard, no NexusDB, no `IN_NEXUS` bootstrap.
 */
final class InstallService
{
    public function __construct(
        private readonly SearchBoxRepositoryInterface $searchBoxes,
        private readonly UserRepositoryInterface $users,
    ) {}

    /** Run database migrations. */
    public function migrate(?string $path = null): void
    {
        $args = ['--force' => true];
        if ($path !== null) {
            $args['--path'] = $path;
        }
        Artisan::call('migrate', $args);
    }

    /** Run the database seeder (settings defaults, taxonomies, panels). */
    public function seed(): void
    {
        Artisan::call('db:seed', ['--force' => true]);
    }

    /**
     * Merge default settings with legacy `config/allconfig.php` values and
     * already-persisted DB settings. Does NOT persist — the caller decides
     * (install saves; upgrade must not overwrite operator settings).
     *
     * @return array{settings: array<string, array<string, mixed>>, symbolic_links: list<string>}
     */
    public function resolveSettings(): array
    {
        $defaultSettingsFile = database_path('settings.default.php');
        $originalConfigFile = base_path('config/allconfig.php');
        if (! file_exists($defaultSettingsFile)) {
            throw new \RuntimeException("default setting file: $defaultSettingsFile not exists.");
        }
        if (! file_exists($originalConfigFile)) {
            throw new \RuntimeException("original setting file: $originalConfigFile not exists.");
        }

        $requireDirs = [
            'main' => ['bitbucket'],
            'attachment' => ['savedirectory'],
        ];
        $symbolicLinks = [];

        require $originalConfigFile;
        $definedVars = get_defined_vars();
        /** @var array<string, array<string, mixed>> $settings */
        $settings = require $defaultSettingsFile;
        $settingsFromDb = [];
        if (Schema::hasTable('settings') && DB::table('settings')->count() > 0) {
            /** @var array<string, array<string, mixed>> $settingsFromDb */
            $settingsFromDb = Settings::fromDb() ?: [];
        }
        foreach ($settings as $prefix => &$group) {
            $prefixUpperCase = strtoupper((string) $prefix);
            $oldGroupValues = $definedVars[$prefixUpperCase] ?? null;
            foreach ($group as $key => &$value) {
                // merge original config or db config to default setting, exclude code part
                if ($prefix !== 'code') {
                    if (isset($settingsFromDb[$prefix][$key])) {
                        $value = $settingsFromDb[$prefix][$key];
                    } elseif (isset($oldGroupValues[$key])) {
                        $value = $oldGroupValues[$key];
                    }
                }
                if ($prefix === 'basic' && is_string($value) && Str::startsWith($value, 'localhost')) {
                    $value = '';
                }
                if (isset($requireDirs[$prefix]) && in_array($key, $requireDirs[$prefix], true)) {
                    $dir = Path::resolve((string) $value, base_path());
                    $symbolicLinks[] = $dir;
                }
            }
        }
        unset($group, $value);

        return [
            'settings' => $settings,
            'symbolic_links' => $symbolicLinks,
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $settings
     * @param  callable(string):void|null  $log
     */
    public function saveSettings(array $settings, ?callable $log = null): void
    {
        if (! Schema::hasTable('settings')) {
            $this->migrate('database/migrations/2021_06_08_113437_create_settings_table.php');
        }
        if (! Schema::hasColumn('settings', 'autoload')) {
            $this->migrate('database/migrations/2022_05_06_191830_add_autoload_to_settings_table.php');
        }
        foreach ($settings as $prefix => $group) {
            $this->log($log, "[SAVE SETTING] prefix: $prefix");
            Settings::saveBatch((string) $prefix, $group, true);
        }
    }

    /**
     * @param  list<string>  $symbolicLinks
     * @param  callable(string):void|null  $log
     */
    public function createSymbolicLinks(array $symbolicLinks, ?callable $log = null): void
    {
        foreach ($symbolicLinks as $path) {
            $linkName = base_path('public/'.basename($path));
            if (is_link($linkName) || is_file($linkName)) {
                unlink($linkName);
                $this->log($log, "path: $linkName already exists, deleted");
            }
            if (is_dir($linkName)) {
                $this->log($log, "path: $linkName already exists, skip create symbolic link $linkName -> $path");

                continue;
            }
            if (! symlink($path, $linkName)) {
                throw new \RuntimeException("can not make symbolic link:  $linkName -> $path");
            }
            $this->log($log, "[CREATE SYMBOLIC LINK] $linkName -> $path");
        }
    }

    public function migrateSearchBoxModeRelated(?callable $log = null): void
    {
        $this->log($log, '[migrateSearchBoxModeRelated]');
        $this->searchBoxes->migrateToModeRelated();
    }

    /**
     * Insert the default tracker announce URL. During install there is no
     * HTTP host, so fall back to APP_URL; during upgrade prefer the
     * configured announce URLs.
     *
     * @param  callable(string):void|null  $log
     */
    public function initTrackerUrl(string $scene, ?callable $log = null): void
    {
        $announceUrl = null;
        if ($scene === 'update') {
            $announceUrl = SiteConfig::current()->security->httpsAnnounceUrl();
            if (empty($announceUrl)) {
                $announceUrl = SiteConfig::current()->basic->announceUrl();
            }
        }
        if (empty($announceUrl)) {
            $host = parse_url((string) config('app.url'), PHP_URL_HOST)
                ?: (string) Env::get('APP_URL', 'localhost');
            $announceUrl = sprintf('%s/%s', trim((string) $host, '/'), trim(DEFAULT_TRACKER_URI, '/'));
        }
        if (! str_starts_with($announceUrl, 'http')) {
            $announceUrl = (Url::isSecure() ? 'https://' : 'http://').$announceUrl;
        }
        TrackerUrl::query()->create([
            'url' => $announceUrl,
            'enabled' => 1,
            'is_default' => 1,
        ]);
        TrackerUrl::saveUrlCache();
        $this->log($log, "[initTrackerUrl] $announceUrl success.");
    }

    /**
     * Create the first staff-leader account. Fails if one already exists.
     */
    public function createAdministrator(string $username, string $email, string $password, string $confirmPassword): User
    {
        $class = UserClassEnum::STAFFLEADER->value;
        if (User::query()->where('class', $class)->count() > 0) {
            throw new \InvalidArgumentException('Administrator already exists');
        }
        $user = $this->users->store([
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $confirmPassword,
            'class' => $class,
            'id' => 1,
        ]);

        return $user;
    }

    /** @param callable(string):void|null $log */
    private function log(?callable $log, string $message): void
    {
        if ($log !== null) {
            $log($message);
        }
    }
}
