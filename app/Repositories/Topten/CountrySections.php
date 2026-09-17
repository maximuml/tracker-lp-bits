<?php

declare(strict_types=1);

namespace App\Repositories\Topten;

use Illuminate\Support\Facades\DB;

/**
 * Top-ten country leaderboard sections — extracted from ToptenRepository to keep both
 * classes under the 400-line ratchet.
 */
final class CountrySections extends SectionQueries
{
    /**
     * @return list<array<string, mixed>>
     */
    public function build(int $limit, ?string $subtype): array
    {
        $sections = [];

        if ($limit === 10 || $subtype === 'us') {
            $sections[] = [
                'view' => 'countries',
                'data' => $this->toArray(
                    DB::table('countries')
                        ->leftJoin('users', 'users.country', '=', 'countries.id')
                        ->select('countries.name', 'countries.flagpic', DB::raw('COUNT(users.country) as num'))
                        ->groupBy('countries.name', 'countries.flagpic')
                        ->orderBy('num', 'desc')
                        ->limit($limit)
                        ->get()
                ),
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_countries_users')),
                'limits' => [25],
                'subtype' => 'us',
                'what' => __('legacy/topten.col_users'),
            ];
        }

        if ($limit === 10 || $subtype === 'ul') {
            $sections[] = [
                'view' => 'countries',
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
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_countries_uploaded')),
                'limits' => [25],
                'subtype' => 'ul',
                'what' => __('legacy/topten.col_uploaded'),
            ];
        }

        if ($limit === 10 || $subtype === 'avg') {
            $sections[] = [
                'view' => 'countries',
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
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_countries_per_user')),
                'limits' => [25],
                'subtype' => 'avg',
                'what' => __('legacy/topten.col_average'),
            ];
        }

        if ($limit === 10 || $subtype === 'r') {
            $sections[] = [
                'view' => 'countries',
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
                'caption' => $this->caption(__('legacy/topten.text_top'), $limit, __('legacy/topten.text_countries_ratio')),
                'limits' => [25],
                'subtype' => 'r',
                'what' => __('legacy/topten.col_ratio'),
            ];
        }

        return $sections;
    }
}
