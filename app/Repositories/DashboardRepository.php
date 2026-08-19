<?php

namespace App\Repositories;

use App\Models\Peer;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use App\Support\SupportContext;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class DashboardRepository extends BaseRepository
{
    /** @return  array<string, array<string, mixed>> */
    public function getSystemInfo(): array
    {
        $result = [];
        $name = 'nexus_version';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => VERSION_NUMBER,
        ];
        $name = 'nexus_release_date';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => RELEASE_DATE,
        ];
        $name = 'laravel_version';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => \Illuminate\Foundation\Application::VERSION,
        ];
        $name = 'filament_version';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => \Composer\InstalledVersions::getPrettyVersion('filament/filament'),
        ];
        $name = 'php_version';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => PHP_VERSION,
        ];
        $name = 'mysql_version';
        $databaseInfo = NexusDB::getDatabaseVersionInfo();
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => sprintf("%s: %s", $databaseInfo['dbType'], $databaseInfo['version']),
        ];
//        $name = 'os';
//        $result[$name] = [
//            'name' => $name,
//            'text' => nexus_trans("dashboard.system_info.$name"),
//            'value' => PHP_OS,
//        ];
        $name = 'redis_version';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' =>  NexusDB::redis()->info()['redis_version'],
        ];

        $name = 'server_software';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' => SupportContext::getServerValue('SERVER_SOFTWARE', ''),
        ];

        $name = 'load_average';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.system_info.{$name}", [], null),
            'value' =>  function_exists('sys_getloadavg') ? (($load = sys_getloadavg()) === false ? 'N/A' : implode(', ', $load)) : 'N/A',
        ];
        return $result;
    }

    /** @return  array<string, array<string, mixed>> */
    public function getStatData(): array
    {
        return [
            'user_class' => [
                'text' => \App\Support\Locale::trans('dashboard.user_class.page_title', [], null),
                'data' => $this->statUserClass()
            ],
            'user' => [
                'text' => \App\Support\Locale::trans('dashboard.user.page_title', [], null),
                'data' => $this->statUsers()
            ],
            'torrent' => [
                'text' => \App\Support\Locale::trans('dashboard.torrent.page_title', [], null),
                'data' => $this->statTorrents()
            ],
            'system_info' => [
                'text' => \App\Support\Locale::trans('dashboard.system_info.page_title', [], null),
                'data' => $this->getSystemInfo()
            ],
        ];
    }

    /** @return  array<int|string, array<string, mixed>> */
    public function statUserClass(): array
    {
        $userClasses = User::query()
            ->groupBy('class')
            ->selectRaw('class, count(*) as counts')
            ->get()
            ->pluck('counts', 'class');
        $result = [];
        foreach (User::$classes as $class => $value) {
            if ($class >= User::CLASS_VIP) {
                break;
            }
            $result[$class] = [
                'name' => $class,
                'text' => $value['text'],
                'value' => $userClasses->has($class) ? number_format($userClasses->get($class)) : 0,
            ];
        }
        return $result;
    }

    /** @return  array<string, array<string, mixed>> */
    public function statUsers(): array
    {
        $result = [];
        $now = Carbon::now();

        $name = 'total';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.user.{$name}", [], null),
            'value' => sprintf('%s / %s', number_format(User::query()->count()), number_format(intval(\App\Support\Config\SiteConfig::current()->main->maxUsers()))),
        ];
        $name = 'unconfirmed';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('status', User::STATUS_PENDING)->count()),
        ];
        $name = 'visit_last_one_day';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('last_access', '>', $now->subDays(1))->count()),
        ];
        $name = 'visit_last_one_week';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('last_access', '>', $now->subDays(7))->count()),
        ];
        $name = 'visit_last_30_days';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('last_access', '>', $now->subDays(30))->count()),
        ];
        $name = 'vip';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('class', User::CLASS_VIP)->count()),
        ];
        $name = 'donated';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('donor', 'yes')->count()),
        ];
        $name = 'warned';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('warned', 'yes')->count()),
        ];
        $name = 'disabled';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('enabled', 'no')->count()),
        ];

        $statGender = User::query()->groupBy('gender')->selectRaw('gender, count(*) as counts')->get()->pluck('counts','gender');
        foreach ($statGender as $gender => $value) {
            if (!isset(User::$genders[$gender])) {
                $gender = User::GENDER_UNKNOWN;
            }
            $name = "gender_$gender";
            $result[$name] = [
                'name' => $name,
                'text' => \App\Support\Locale::trans("dashboard.user.{$name}", [], null),
                'value' => $statGender->has($gender) ? number_format($statGender->get($gender)) : 0,
            ];
        }
        return $result;
    }

    /** @return  array<string, array<string, mixed>> */
    public function statTorrents(): array
    {
        $now = now();
        $name = 'total';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format(Torrent::query()->count()),
        ];
        $name = 'dead';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format(Torrent::query()->where('visible', '=', Torrent::VISIBLE_NO)->count()),
        ];

        $seeders = Peer::query()->where('seeder', 'yes')->count();
        $name = 'seeders';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format($seeders),
        ];

        $leechers = Peer::query()->where('seeder', 'no')->count();
        $name = 'leechers';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format($leechers),
        ];
        $name = 'seeders_leechers';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format($seeders + $leechers),
        ];
        $name = 'seeders_leechers_ratio';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => $leechers == 0 ? 0 : number_format(($seeders / $leechers) * 100) . '%',
        ];
        $name = 'active_web_users';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format(User::query()->where('last_access', '>', $now->subSeconds(900))->count()),
        ];
        $name = 'active_tracker_users';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format((float) (($peer = Peer::query()->selectRaw('count(distinct(userid)) as counts')->first()) ? (int) $peer->counts : 0)),
        ];

        $name = 'total_torrent_size';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => \App\Support\Format::size((float) (Torrent::query()->sum('size') ?? 0)),
        ];

        $total_uploaded_byte = User::query()->sum('uploaded');
        $total_downloaded_byte = User::query()->sum('downloaded');
        $total_byte = $total_uploaded_byte + $total_downloaded_byte;

        $name = 'total_uploaded';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => \App\Support\Format::size((float) $total_uploaded_byte),
        ];
        $name = 'total_downloaded';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => \App\Support\Format::size((float) $total_downloaded_byte),
        ];
        $name = 'total_uploaded_downloaded';
        $result[$name] = [
            'name' => $name,
            'text' => \App\Support\Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => \App\Support\Format::size((float) $total_byte),
        ];

        return $result;
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function statTracker(): array
    {
        $now = Carbon::now();

        $result = [];
        $result[] = [
            'name' => 'total_torrents',
            'text' => __('dashboard.tracker.total_torrents'),
            'value' => number_format(Torrent::query()->count()),
        ];
        $result[] = [
            'name' => 'total_peers',
            'text' => __('dashboard.tracker.total_peers'),
            'value' => number_format(Peer::query()->count()),
        ];
        $result[] = [
            'name' => 'seeders',
            'text' => __('dashboard.tracker.seeders'),
            'value' => number_format(Peer::query()->where('seeder', 'yes')->count()),
        ];
        $result[] = [
            'name' => 'leechers',
            'text' => __('dashboard.tracker.leechers'),
            'value' => number_format(Peer::query()->where('seeder', 'no')->count()),
        ];
        $result[] = [
            'name' => 'total_users',
            'text' => __('dashboard.tracker.total_users'),
            'value' => number_format(User::query()->count()),
        ];
        $result[] = [
            'name' => 'users_online',
            'text' => __('dashboard.tracker.users_online'),
            'value' => number_format(User::query()->where('last_access', '>', $now->subSeconds(900))->count()),
        ];
        $result[] = [
            'name' => 'total_uploaded',
            'text' => __('dashboard.tracker.total_uploaded'),
            'value' => \App\Support\Format::size((float) User::query()->sum('uploaded')),
        ];
        $result[] = [
            'name' => 'total_downloaded',
            'text' => __('dashboard.tracker.total_downloaded'),
            'value' => \App\Support\Format::size((float) User::query()->sum('downloaded')),
        ];

        return $result;
    }

}
