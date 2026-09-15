<?php

declare(strict_types=1);

namespace App\Repositories\Topten;

use Illuminate\Support\Facades\DB;

/**
 * Top-ten misc leaderboard sections — extracted from ToptenRepository to keep both
 * classes under the 400-line ratchet.
 */
final class OtherSections extends SectionQueries
{
    /**
     * @param  array<string, mixed>  $lang
     * @return list<array<string, mixed>>
     */
    public function build(int $limit, ?string $subtype, array $lang, bool $enabledDonation): array
    {
        $sections = [];

        if ($limit === 10 || $subtype === 'bo') {
            $sections[] = [
                'view' => 'bonus',
                'data' => $this->toArray(DB::table('users')->select('id', 'seedbonus')->orderBy('seedbonus', 'desc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_most_bonuses'] ?? 'Bonuses'),
                'limits' => [100, 250],
                'subtype' => 'bo',
            ];
        }

        if ($limit === 10 || $subtype === 'charity') {
            $sections[] = [
                'view' => 'charity',
                'data' => $this->toArray(DB::table('users')->select('id', 'charity')->orderBy('charity', 'desc')->limit($limit)->get()),
                'caption' => $this->caption($lang['text_top'] ?? 'Top ', $limit, $lang['text_charity_giver'] ?? 'Charity Givers'),
                'limits' => [100, 250],
                'subtype' => 'charity',
            ];
        }

        if ($enabledDonation) {
            if ($limit === 10 || $subtype === 'do_usd') {
                $sections[] = [
                    'view' => 'donors',
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
                    'view' => 'donors',
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
                'view' => 'clients',
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
                'view' => 'stylesheet',
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
                'view' => 'language',
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
}
