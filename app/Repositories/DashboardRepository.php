<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Torrent;
use App\Models\User;
use App\Support\Database;
use App\Support\Input;
use App\Support\Locale;
use Composer\InstalledVersions;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Redis;

class DashboardRepository extends BaseRepository
{
    public function __construct(
        private readonly DashboardStatsRepository $statsRepository = new DashboardStatsRepository,
    ) {}

    /** @return  array<string, array<string, mixed>> */
    public function getSystemInfo(): array
    {
        $result = [];
        $name = 'nexus_version';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => VERSION_NUMBER,
        ];
        $name = 'nexus_release_date';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => RELEASE_DATE,
        ];
        $name = 'laravel_version';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => Application::VERSION,
        ];
        $name = 'filament_version';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => InstalledVersions::getPrettyVersion('filament/filament'),
        ];
        $name = 'php_version';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => PHP_VERSION,
        ];
        $name = 'mysql_version';
        $databaseInfo = Database::versionInfo();
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => sprintf('%s: %s', $databaseInfo['dbType'], $databaseInfo['version']),
        ];
        $name = 'redis_version';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => Redis::connection()->client()->info()['redis_version'],
        ];

        $name = 'server_software';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => Input::serverValue('SERVER_SOFTWARE', ''),
        ];

        $name = 'load_average';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => function_exists('sys_getloadavg') ? (($load = sys_getloadavg()) === false ? 'N/A' : implode(', ', $load)) : 'N/A',
        ];

        return $result;
    }

    /** @return  array<string, array<string, mixed>> */
    public function getStatData(): array
    {
        return [
            'user_class' => [
                'text' => Locale::trans('dashboard.user_class.page_title', [], null),
                'data' => $this->statUserClass(),
            ],
            'user' => [
                'text' => Locale::trans('dashboard.user.page_title', [], null),
                'data' => $this->statUsers(),
            ],
            'torrent' => [
                'text' => Locale::trans('dashboard.torrent.page_title', [], null),
                'data' => $this->statTorrents(),
            ],
            'system_info' => [
                'text' => Locale::trans('dashboard.system_info.page_title', [], null),
                'data' => $this->getSystemInfo(),
            ],
        ];
    }

    /** @return  mixed */
    public function latestUser()
    {
        return User::query()->orderBy('id', 'desc')->limit(10)->get(User::$commonFields);
    }

    /** @return  mixed */
    public function latestTorrent()
    {
        return Torrent::query()->with(['user'])->orderBy('id', 'desc')->limit(5)->get(Torrent::$commentFields);
    }

    /** @return  array<int|string, array<string, mixed>> */
    public function statUserClass(): array
    {
        return $this->statsRepository->statUserClass();
    }

    /** @return  array<string, array<string, mixed>> */
    public function statUsers(): array
    {
        return $this->statsRepository->statUsers();
    }

    /** @return  array<string, array<string, mixed>> */
    public function statTorrents(): array
    {
        return $this->statsRepository->statTorrents();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function statTracker(): array
    {
        return $this->statsRepository->statTracker();
    }

    /**
     * Uploader activity table (mirrors legacy stats page uploaders section).
     *
     * @return array<int, array<string, mixed>>
     */
    public function uploaderActivity(): array
    {
        return $this->statsRepository->uploaderActivity();
    }

    /**
     * Category activity table (mirrors legacy stats page categories section).
     *
     * @return array<int, array<string, mixed>>
     */
    public function categoryActivity(): array
    {
        return $this->statsRepository->categoryActivity();
    }

    /**
     * Peer agents summary (mirrors legacy allagents page).
     *
     * @return array<int, array<string, mixed>>
     */
    public function peerAgents(): array
    {
        return $this->statsRepository->peerAgents();
    }

    /**
     * Donor summary (mirrors legacy donorlist page).
     *
     * @return array<int, array<string, mixed>>
     */
    public function donorSummary(): array
    {
        return $this->statsRepository->donorSummary();
    }
}
