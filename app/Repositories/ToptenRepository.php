<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\Globals;
use App\Support\Settings;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ToptenRepository
{
    /**
     * @return array<string, mixed>
     */
    public function page(int $type, int $limit, ?string $subtype): array
    {
        if (! in_array($type, [1, 2, 3, 5, 6], true)) {
            $type = 1;
        }

        if ($limit < 1 || $limit > 250) {
            $limit = 10;
        }

        $lang = (array) app(Globals::class)->get('lang_topten', []);
        $enabledDonation = ((string) Settings::get('main.donation', 'no')) === 'yes';
        $dateFounded = (string) app(Globals::class)->get('datefounded', '');

        $sections = match ($type) {
            1 => $this->userSections($limit, $subtype, $lang),
            2 => $this->torrentSections($limit, $subtype, $lang),
            3 => $this->countrySections($limit, $subtype, $lang),
            5 => $this->communitySections($limit, $subtype, $lang),
            6 => $this->otherSections($limit, $subtype, $lang, $enabledDonation),
        };

        return [
            'type' => $type,
            'limit' => $limit,
            'subtype' => $subtype,
            'enabledDonation' => $enabledDonation,
            'dateFounded' => $dateFounded,
            'lang' => $lang,
            'sections' => $sections,
        ];
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return list<array<string, mixed>>
     */
    private function userSections(int $limit, ?string $subtype, array $lang): array
    {
        $base = $this->userBaseQuery();
        $sections = [];

        if ($limit === 10 || $subtype === 'ul') {
            $sections[] = [
                'renderer' => 'usershare_table',
                'data' => $this->toArray((clone $base)->orderBy('uploaded', 'desc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_uploaders'] ?? 'Uploaders'),
                'limits' => [100, 250],
                'subtype' => 'ul',
            ];
        }

        if ($limit === 10 || $subtype === 'dl') {
            $sections[] = [
                'renderer' => 'usershare_table',
                'data' => $this->toArray((clone $base)->orderBy('downloaded', 'desc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_downloaders'] ?? 'Downloaders'),
                'limits' => [100, 250],
                'subtype' => 'dl',
            ];
        }

        if ($limit === 10 || $subtype === 'uls') {
            $note = $lang['text_fastest_up_note'] ?? '';
            $sections[] = [
                'renderer' => 'usershare_table',
                'data' => $this->toArray((clone $base)->where('uploaded', '>', 53687091200)->orderBy('upspeed', 'desc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_fastest_uploaders'] ?? 'Fastest Uploaders', $note),
                'limits' => [100, 250],
                'subtype' => 'uls',
            ];
        }

        if ($limit === 10 || $subtype === 'dls') {
            $note = $lang['text_fastest_note'] ?? '';
            $sections[] = [
                'renderer' => 'usershare_table',
                'data' => $this->toArray((clone $base)->orderBy('downspeed', 'desc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_fastest_downloaders'] ?? 'Fastest Downloaders', $note),
                'limits' => [100, 250],
                'subtype' => 'dls',
            ];
        }

        if ($limit === 10 || $subtype === 'bsh') {
            $sections[] = [
                'renderer' => 'usershare_table',
                'data' => $this->toArray((clone $base)->where('downloaded', '>', 53687091200)->orderByRaw('uploaded / downloaded DESC')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_best_sharers'] ?? 'Best Sharers', $lang['text_sharers_note'] ?? ''),
                'limits' => [100, 250],
                'subtype' => 'bsh',
            ];
        }

        if ($limit === 10 || $subtype === 'wsh') {
            $sections[] = [
                'renderer' => 'usershare_table',
                'data' => $this->toArray((clone $base)->where('downloaded', '>', 53687091200)->orderByRaw('uploaded / downloaded ASC, downloaded DESC')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_worst_sharers'] ?? 'Worst Sharers', $lang['text_sharers_note'] ?? ''),
                'limits' => [100, 250],
                'subtype' => 'wsh',
            ];
        }

        return $sections;
    }

    private function userBaseQuery(): Builder
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            $speedStr = 'uploaded / (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(added)) AS upspeed, downloaded / (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(added)) AS downspeed';
        } elseif (DB::connection()->getDriverName() === 'pgsql') {
            $speedStr = 'uploaded::numeric / (EXTRACT(EPOCH FROM NOW()) - EXTRACT(EPOCH FROM added)) AS upspeed, downloaded::numeric / (EXTRACT(EPOCH FROM NOW()) - EXTRACT(EPOCH FROM added)) AS downspeed';
        } else {
            throw new \RuntimeException('Unsupported database driver for top-ten speed calculation.');
        }

        return DB::table('users')
            ->selectRaw("id as userid, username, added, uploaded, downloaded, {$speedStr}")
            ->where('enabled', true);
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return list<array<string, mixed>>
     */
    private function torrentSections(int $limit, ?string $subtype, array $lang): array
    {
        $base = DB::table('torrents as t')
            ->leftJoin('peers as p', 't.id', '=', 'p.torrent')
            ->selectRaw('t.*, (t.size * t.times_completed + SUM(p.downloaded)) AS data')
            ->where('p.seeder', 0)
            ->groupBy('t.id');

        $sections = [];

        if ($limit === 10 || $subtype === 'act') {
            $sections[] = [
                'renderer' => '_torrenttable',
                'data' => $this->toArray((clone $base)->orderByRaw('seeders + leechers DESC, seeders DESC, added ASC')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_active_torrents'] ?? 'Most Active Torrents'),
                'limits' => [25, 50],
                'subtype' => 'act',
            ];
        }

        if ($limit === 10 || $subtype === 'sna') {
            $sections[] = [
                'renderer' => '_torrenttable',
                'data' => $this->toArray((clone $base)->orderBy('times_completed', 'desc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_snatched_torrents'] ?? 'Most Snatched Torrents'),
                'limits' => [25, 50],
                'subtype' => 'sna',
            ];
        }

        if ($limit === 10 || $subtype === 'mdt') {
            $sections[] = [
                'renderer' => '_torrenttable',
                'data' => $this->toArray((clone $base)->where('times_completed', '>', 0)->orderBy('data', 'desc')->orderBy('added', 'asc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_data_transferred_torrents'] ?? 'Most Data Transferred Torrents'),
                'limits' => [25, 50],
                'subtype' => 'mdt',
            ];
        }

        if ($limit === 10 || $subtype === 'bse') {
            $sections[] = [
                'renderer' => '_torrenttable',
                'data' => $this->toArray((clone $base)->where('seeders', '>=', 5)->orderByRaw('seeders / leechers DESC, seeders DESC, added ASC')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_best_seeded_torrents'] ?? 'Best Seeded Torrents', $lang['text_best_seeded_torrents_note'] ?? ''),
                'limits' => [25, 50],
                'subtype' => 'bse',
            ];
        }

        if ($limit === 10 || $subtype === 'wse') {
            $sections[] = [
                'renderer' => '_torrenttable',
                'data' => $this->toArray(
                    DB::table('torrents as t')
                        ->selectRaw('t.*, (t.size * t.times_completed) AS data')
                        ->where('leechers', '>', 0)
                        ->where('times_completed', '>', 0)
                        ->orderByRaw('seeders / leechers ASC, leechers DESC')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_worst_seeded_torrents'] ?? 'Worst Seeded Torrents', $lang['text_worst_seeded_torrents_note'] ?? ''),
                'limits' => [25, 50],
                'subtype' => 'wse',
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return list<array<string, mixed>>
     */
    private function countrySections(int $limit, ?string $subtype, array $lang): array
    {
        $sections = [];

        if ($limit === 10 || $subtype === 'us') {
            $sections[] = [
                'renderer' => 'countriestable',
                'data' => $this->toArray(
                    DB::table('countries')
                        ->leftJoin('users', 'users.country', '=', 'countries.id')
                        ->select('countries.name', 'countries.flagpic', DB::raw('COUNT(users.country) as num'))
                        ->groupBy('countries.name', 'countries.flagpic')
                        ->orderBy('num', 'desc')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_countries_users'] ?? 'Users'),
                'limits' => [25],
                'subtype' => 'us',
                'what' => $lang['col_users'] ?? 'Users',
            ];
        }

        if ($limit === 10 || $subtype === 'ul') {
            $sections[] = [
                'renderer' => 'countriestable',
                'data' => $this->toArray(
                    DB::table('users as u')
                        ->leftJoin('countries as c', 'u.country', '=', 'c.id')
                        ->select('c.name', 'c.flagpic', DB::raw('sum(u.uploaded) AS ul'))
                        ->where('u.enabled', true)
                        ->groupBy('c.id', 'c.name', 'c.flagpic')
                        ->orderBy('ul', 'desc')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_countries_uploaded'] ?? 'Total Uploaded'),
                'limits' => [25],
                'subtype' => 'ul',
                'what' => $lang['col_uploaded'] ?? 'Uploaded',
            ];
        }

        if ($limit === 10 || $subtype === 'avg') {
            $sections[] = [
                'renderer' => 'countriestable',
                'data' => $this->toArray(
                    DB::table('users as u')
                        ->leftJoin('countries as c', 'u.country', '=', 'c.id')
                        ->select('c.name', 'c.flagpic', DB::raw('sum(u.uploaded)/count(u.id) AS ul_avg'))
                        ->where('u.enabled', true)
                        ->groupBy('c.id', 'c.name', 'c.flagpic')
                        ->havingRaw('sum(u.uploaded) > 1099511627776 AND count(u.id) >= 100')
                        ->orderBy('ul_avg', 'desc')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_countries_per_user'] ?? 'Average Total Uploaded Per User'),
                'limits' => [25],
                'subtype' => 'avg',
                'what' => $lang['col_average'] ?? 'Average',
            ];
        }

        if ($limit === 10 || $subtype === 'r') {
            $sections[] = [
                'renderer' => 'countriestable',
                'data' => $this->toArray(
                    DB::table('users as u')
                        ->leftJoin('countries as c', 'u.country', '=', 'c.id')
                        ->select('c.name', 'c.flagpic', DB::raw('sum(u.uploaded)/sum(u.downloaded) AS r'))
                        ->where('u.enabled', true)
                        ->groupBy('c.id', 'c.name', 'c.flagpic')
                        ->havingRaw('sum(u.uploaded) > 1099511627776 AND sum(u.downloaded) > 1099511627776 AND count(u.id) >= 100')
                        ->orderBy('r', 'desc')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_countries_ratio'] ?? 'Ratio'),
                'limits' => [25],
                'subtype' => 'r',
                'what' => $lang['col_ratio'] ?? 'Ratio',
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return list<array<string, mixed>>
     */
    private function communitySections(int $limit, ?string $subtype, array $lang): array
    {
        $sections = [];

        $postBase = DB::table('users as u')
            ->leftJoin('topics', 'u.id', '=', 'topics.userid')
            ->leftJoin('posts', 'u.id', '=', 'posts.userid')
            ->select('u.id as userid', DB::raw('COUNT(DISTINCT topics.id) as usertopics'), DB::raw('COUNT(DISTINCT posts.id) as userposts'))
            ->groupBy('u.id');

        if ($limit === 10 || $subtype === 'mtop') {
            $sections[] = [
                'renderer' => 'postable',
                'data' => $this->toArray((clone $postBase)->orderBy('usertopics', 'desc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_topic'] ?? ' Forum Topic Starters '),
                'limits' => [100, 250],
                'subtype' => 'mtop',
            ];
        }

        if ($limit === 10 || $subtype === 'mpos') {
            $sections[] = [
                'renderer' => 'postable',
                'data' => $this->toArray((clone $postBase)->orderBy('userposts', 'desc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_post'] ?? ' Forum Posters '),
                'limits' => [100, 250],
                'subtype' => 'mpos',
            ];
        }

        if ($limit === 10 || $subtype === 'mcmt') {
            $sections[] = [
                'renderer' => 'cmttable',
                'data' => $this->toArray(
                    DB::table('users')
                        ->leftJoin('comments', 'users.id', '=', 'comments.user')
                        ->select('users.id as userid', DB::raw('COUNT(comments.id) as num'))
                        ->groupBy('users.id')
                        ->orderBy('num', 'desc')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_commenter'] ?? 'Torrent Commenter '),
                'limits' => [100, 250],
                'subtype' => 'mcmt',
                'what' => $lang['col_comments'] ?? 'Comments',
            ];
        }

        if ($limit === 10 || $subtype === 'btop') {
            $sections[] = [
                'renderer' => 'bigtopic_table',
                'data' => $this->toArray(
                    DB::table('topics as tp')
                        ->leftJoin('posts', 'tp.id', '=', 'posts.topicid')
                        ->leftJoin('forums', 'tp.forumid', '=', 'forums.id')
                        ->select('tp.id as topicid', 'tp.subject as topicsubject', DB::raw('COUNT(posts.id) as postnum'), 'tp.forumid', 'forums.id as forumid')
                        ->where('forums.minclassread', '<=', 1)
                        ->orWhereNull('forums.id')
                        ->groupBy('tp.id', 'tp.subject', 'tp.forumid', 'forums.id')
                        ->orderBy('postnum', 'desc')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_biggest_topics'] ?? 'Biggest Topics'),
                'limits' => [100, 250],
                'subtype' => 'btop',
            ];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $lang
     * @return list<array<string, mixed>>
     */
    private function otherSections(int $limit, ?string $subtype, array $lang, bool $enabledDonation): array
    {
        $sections = [];

        if ($limit === 10 || $subtype === 'bo') {
            $sections[] = [
                'renderer' => 'bonustable',
                'data' => $this->toArray(DB::table('users')->select('id', 'seedbonus')->orderBy('seedbonus', 'desc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_bonuses'] ?? 'Bonuses'),
                'limits' => [100, 250],
                'subtype' => 'bo',
            ];
        }

        if ($limit === 10 || $subtype === 'charity') {
            $sections[] = [
                'renderer' => 'charityTable',
                'data' => $this->toArray(DB::table('users')->select('id', 'charity')->orderBy('charity', 'desc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_charity_giver'] ?? 'Charity Givers'),
                'limits' => [100, 250],
                'subtype' => 'charity',
            ];
        }

        if ($enabledDonation) {
            if ($limit === 10 || $subtype === 'do_usd') {
                $sections[] = [
                    'renderer' => 'donortable',
                    'data' => $this->toArray(
                        DB::table('users')
                            ->select('id', 'donated', 'donated_cny')
                            ->where('donated', '>', 0)
                            ->orderBy('donated', 'desc')->orderBy('donated_cny', 'desc')
                            ->limit($limit)
                            ->get()
                    ),
                    'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_donated_USD'] ?? 'Donors in US dollar'),
                    'limits' => [100, 250],
                    'subtype' => 'do_usd',
                ];
            }

            if ($limit === 10 || $subtype === 'do_cny') {
                $sections[] = [
                    'renderer' => 'donortable',
                    'data' => $this->toArray(
                        DB::table('users')
                            ->select('id', 'donated', 'donated_cny')
                            ->where('donated_cny', '>', 0)
                            ->orderBy('donated', 'desc')->orderBy('donated_cny', 'desc')
                            ->limit($limit)
                            ->get()
                    ),
                    'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_donated_CNY'] ?? 'Donors in Chinese yuan'),
                    'limits' => [100, 250],
                    'subtype' => 'do_cny',
                ];
            }
        }

        if ($limit === 10 || $subtype === 'mcli') {
            $sections[] = [
                'renderer' => 'clienttable',
                'data' => $this->toArray(
                    DB::table('users')
                        ->rightJoin('agent_allowed_family', 'users.clientselect', '=', 'agent_allowed_family.id')
                        ->select('agent_allowed_family.family as client_name', DB::raw('COUNT(users.id) as client_num'))
                        ->groupBy('users.clientselect', 'agent_allowed_family.family')
                        ->orderBy('client_num', 'desc')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_client'] ?? 'Torrent Clients '),
                'limits' => [100, 250],
                'subtype' => 'mcli',
            ];
        }

        if ($limit === 10 || $subtype === 'ss') {
            $sections[] = [
                'renderer' => 'stylesheettable',
                'data' => $this->toArray(
                    DB::table('users')
                        ->join('stylesheets', 'users.stylesheet', '=', 'stylesheets.id')
                        ->select('stylesheets.name as stylesheet_name', DB::raw('COUNT(users.id) as stylesheet_num'))
                        ->groupBy('users.stylesheet', 'stylesheets.name')
                        ->orderBy('stylesheet_num', 'desc')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_stylesheet'] ?? 'Stylesheets'),
                'limits' => [25, 50],
                'subtype' => 'ss',
            ];
        }

        if ($limit === 10 || $subtype === 'lang') {
            $sections[] = [
                'renderer' => 'languagetable',
                'data' => $this->toArray(
                    DB::table('users')
                        ->join('language', 'users.lang', '=', 'language.id')
                        ->select('language.lang_name as lang_name', DB::raw('COUNT(users.id) as lang_num'))
                        ->where('language.site_lang', 1)
                        ->groupBy('users.lang', 'language.lang_name')
                        ->orderBy('lang_num', 'desc')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_language'] ?? 'User Languages'),
                'limits' => [25],
                'subtype' => 'lang',
            ];
        }

        return $sections;
    }

    private function caption(string $topPrefix, int $limit, string $label, ?string $note = null): string
    {
        $html = $topPrefix.$limit.' '.$label;

        if ($note !== null && $note !== '') {
            $html .= '<font class="small">'.$note.'</font>';
        }

        return $html;
    }

    /**
     * @param  Collection<int, \stdClass>  $rows
     * @return list<array<string, mixed>>
     */
    private function toArray(Collection $rows): array
    {
        return array_values($rows->map(fn ($row) => (array) $row)->all());
    }
}
