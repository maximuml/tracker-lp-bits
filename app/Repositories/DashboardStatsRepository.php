<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\TorrentVisible;
use App\Enums\UserClass as UserClassEnum;
use App\Enums\UserGender;
use App\Enums\UserStatus;
use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Locale;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard statistics queries: user-class/user/torrent counts, tracker
 * totals, uploader/category activity, peer agents and donor summary.
 *
 * Extracted from DashboardRepository to keep both classes under the
 * 400-line ratchet.
 */
class DashboardStatsRepository
{
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
            if ($class >= UserClassEnum::VIP->value) {
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
            'text' => Locale::trans("dashboard.user.{$name}", [], null),
            'value' => sprintf('%s / %s', number_format(User::query()->count()), number_format(intval(SiteConfig::current()->main->maxUsers()))),
        ];
        $name = 'unconfirmed';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('status', UserStatus::PENDING->value)->count()),
        ];
        $name = 'visit_last_one_day';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('last_access', '>', $now->subDays(1))->count()),
        ];
        $name = 'visit_last_one_week';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('last_access', '>', $now->subDays(7))->count()),
        ];
        $name = 'visit_last_30_days';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('last_access', '>', $now->subDays(30))->count()),
        ];
        $name = 'vip';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('class', UserClassEnum::VIP->value)->count()),
        ];
        $name = 'donated';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('donor', true)->count()),
        ];
        $name = 'warned';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('warned', true)->count()),
        ];
        $name = 'disabled';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.user.{$name}", [], null),
            'value' => number_format(User::query()->where('enabled', false)->count()),
        ];

        $statGender = DB::table('users')->groupBy('gender')->selectRaw('gender, count(*) as counts')->pluck('counts', 'gender');
        foreach ($statGender as $gender => $value) {
            $genderKey = (int) $gender;
            if (! isset(User::$genders[$genderKey])) {
                $genderKey = UserGender::UNKNOWN->value;
            }
            $name = "gender_$genderKey";
            $result[$name] = [
                'name' => $name,
                'text' => Locale::trans("dashboard.user.{$name}", [], null),
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
            'text' => Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format(Torrent::query()->count()),
        ];
        $name = 'dead';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format(Torrent::query()->where('visible', '=', TorrentVisible::NO->value)->count()),
        ];

        $seeders = Peer::query()->where('seeder', 1)->count();
        $name = 'seeders';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format($seeders),
        ];

        $leechers = Peer::query()->where('seeder', 0)->count();
        $name = 'leechers';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format($leechers),
        ];
        $name = 'seeders_leechers';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format($seeders + $leechers),
        ];
        $name = 'seeders_leechers_ratio';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => $leechers == 0 ? 0 : number_format(($seeders / $leechers) * 100).'%',
        ];
        $name = 'active_web_users';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format(User::query()->where('last_access', '>', $now->subSeconds(900))->count()),
        ];
        $name = 'active_tracker_users';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => number_format((float) (($peer = Peer::query()->selectRaw('count(distinct(userid)) as counts')->first()) ? (int) $peer->counts : 0)),
        ];

        $name = 'total_torrent_size';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => Format::size((float) (Torrent::query()->sum('size') ?? 0)),
        ];

        $total_uploaded_byte = User::query()->sum('uploaded');
        $total_downloaded_byte = User::query()->sum('downloaded');
        $total_byte = $total_uploaded_byte + $total_downloaded_byte;

        $name = 'total_uploaded';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => Format::size((float) $total_uploaded_byte),
        ];
        $name = 'total_downloaded';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => Format::size((float) $total_downloaded_byte),
        ];
        $name = 'total_uploaded_downloaded';
        $result[$name] = [
            'name' => $name,
            'text' => Locale::trans("dashboard.torrent.{$name}", [], null),
            'value' => Format::size((float) $total_byte),
        ];

        return $result;
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
            'value' => number_format(Peer::query()->where('seeder', 1)->count()),
        ];
        $result[] = [
            'name' => 'leechers',
            'text' => __('dashboard.tracker.leechers'),
            'value' => number_format(Peer::query()->where('seeder', 0)->count()),
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
            'value' => Format::size((float) User::query()->sum('uploaded')),
        ];
        $result[] = [
            'name' => 'total_downloaded',
            'text' => __('dashboard.tracker.total_downloaded'),
            'value' => Format::size((float) User::query()->sum('downloaded')),
        ];

        return $result;
    }

    /**
     * Uploader activity table (mirrors legacy stats page uploaders section).
     *
     * @return array<int, array<string, mixed>>
     */
    public function uploaderActivity(): array
    {
        $base = DB::table('users as u')
            ->selectRaw('u.id, u.username AS name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) AS n_p')
            ->leftJoin('torrents as t', 'u.id', '=', 't.owner')
            ->leftJoin('peers as p', 't.id', '=', 'p.torrent');

        $first = clone $base;
        $first->where('u.class', 3)->groupBy('u.id');

        $second = clone $base;
        $second->where('u.class', '>', 3)->groupBy('u.id');

        $rows = $first->union($second)->orderBy('name')->get();
        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'name' => 'uploader',
                'text' => $row->name,
                'value' => sprintf('%s torrents / %s peers / %s', $row->n_t, $row->n_p, $row->last ?? '—'),
            ];
        }

        return $result;
    }

    /**
     * Category activity table (mirrors legacy stats page categories section).
     *
     * @return array<int, array<string, mixed>>
     */
    public function categoryActivity(): array
    {
        $rows = DB::table('categories as c')
            ->selectRaw('c.name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) AS n_p')
            ->leftJoin('torrents as t', 't.category', '=', 'c.id')
            ->leftJoin('peers as p', 't.id', '=', 'p.torrent')
            ->groupBy('c.id')
            ->orderBy('c.name')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'name' => 'category',
                'text' => $row->name,
                'value' => sprintf('%s torrents / %s peers / %s', $row->n_t, $row->n_p, $row->last ?? '—'),
            ];
        }

        return $result;
    }

    /**
     * Peer agents summary (mirrors legacy allagents page).
     *
     * @return array<int, array<string, mixed>>
     */
    public function peerAgents(): array
    {
        $rows = DB::table('peers')
            ->selectRaw('agent, count(*) as counts')
            ->groupBy('agent')
            ->orderBy('agent')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'name' => 'agent',
                'text' => $row->agent ?: '(unknown)',
                'value' => number_format((int) $row->counts),
            ];
        }

        return $result;
    }

    /**
     * Donor summary (mirrors legacy donorlist page).
     *
     * @return array<int, array<string, mixed>>
     */
    public function donorSummary(): array
    {
        $rows = User::query()
            ->where('donor', true)
            ->orderByDesc('donated')
            ->limit(20)
            ->get(['id', 'username', 'donated', 'donated_cny', 'donoruntil']);

        $result = [];
        foreach ($rows as $row) {
            $until = '—';
            if ($row->donoruntil) {
                $timestamp = strtotime((string) $row->donoruntil);
                if ($timestamp !== false) {
                    $until = date('Y-m-d', $timestamp);
                }
            }
            $result[] = [
                'name' => 'donor',
                'text' => $row->username,
                'value' => sprintf('$%s / ¥%s / until %s', number_format((float) $row->donated, 2), number_format((float) $row->donated_cny, 2), $until),
            ];
        }

        return $result;
    }
}
