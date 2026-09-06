<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Bookmark;
use App\Models\Category;
use App\Models\User;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class MeiliSearchFilterService
{
    /** @var array<int|string, mixed> */
    private static array $queryFieldToTorrentFieldMaps = [
        'cat' => 'category',
        'source' => 'source',
        'medium' => 'medium',
        'codec' => 'codec',
        'audiocodec' => 'audiocodec',
        'standard' => 'standard',
        'processing' => 'processing',
    ];

    /**
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    public function getFilters(array $params, User $user): array
    {
        $filters = [];
        $taxonomies = [];
        $categoryIdArr = [];
        // [cat401][cat404][sou1][med1][cod1][sta2][sta3][pro2][tea2][aud2][incldead=0][spstate=3][inclbookmarked=2]
        $userSetting = (string) $user->notifs;
        // cat401=1&source2=1&medium10=1&codec2=1&audiocodec2=1&standard3=1&processing2=1&incldead=2&spstate=1&inclbookmarked=0&approval_status=&size_begin=&size_end=&seeders_begin=&seeders_end=&leechers_begin=&leechers_end=&times_completed_begin=&times_completed_end=&added_begin=&added_end=&search=a+b&search_area=0&search_mode=2
        $queryString = http_build_query($params);
        // section
        if (! empty($params['mode'])) {
            $categoryIdArr = Category::query()->whereIn('mode', Arr::wrap($params['mode']))->pluck('id')->toArray();
        }
        foreach (self::$queryFieldToTorrentFieldMaps as $queryField => $torrentField) {
            if (isset($params[$queryField]) && $params[$queryField] !== '') {
                $taxonomies[$torrentField][] = $params[$queryField];
                Logger::writeWithContext((string) "{$torrentField} from params through {$queryField}: {$params[$queryField]}", (string) 'info', (bool) false);
            } elseif (preg_match_all("/{$queryField}(\d+)=/", $queryString, $matches)) {
                if (count($matches) == 2 && ! empty($matches[1])) {
                    foreach ($matches[1] as $match) {
                        $taxonomies[$torrentField][] = $match;
                        Logger::writeWithContext((string) "{$torrentField} from params through {$queryField}: {$match}", (string) 'info', (bool) false);
                    }
                }
            } else {
                // get user setting
                $pattern = sprintf("/\[%s([\d]+)\]/", substr((string) $queryField, 0, 3));
                if (preg_match($pattern, $userSetting, $matches)) {
                    if (count($matches) == 2 && ! empty($matches[1])) {
                        $match = $matches[1];
                        $taxonomies[$torrentField][] = $match;
                        Logger::writeWithContext((string) "{$torrentField} from user setting through {$queryField}: {$match}", (string) 'info', (bool) false);
                    }
                }
            }
        }
        if (empty($taxonomies['category']) && ! empty($categoryIdArr)) {
            // Restricted to the category of the specified section
            $taxonomies['category'] = $categoryIdArr;
        }
        foreach ($taxonomies as $key => $values) {
            if (! empty($values)) {
                $filters[] = sprintf('%s IN [%s]', $key, implode(', ', array_map('intval', $values)));
            }
        }

        $includeDead = 1;
        if (isset($params['incldead'])) {
            $includeDead = (int) $params['incldead'];
        } elseif (preg_match("/\[incldead=(\d+)\]/", $userSetting, $matches)) {
            $includeDead = $matches[1];
        }
        if ($includeDead == 1) {
            // active torrent
            $filters[] = 'visible = 1';
            Logger::writeWithContext((string) "visible = yes through incldead: {$includeDead}", (string) 'info', (bool) false);
        } elseif ($includeDead == 2) {
            // dead torrent
            $filters[] = 'visible = 0';
            Logger::writeWithContext((string) "visible = no through incldead: {$includeDead}", (string) 'info', (bool) false);
        }

        $includeBookmarked = 0;
        if (isset($params['inclbookmarked'])) {
            $includeBookmarked = (int) $params['inclbookmarked'];
        } elseif (preg_match("/\[inclbookmarked=(\d+)\]/", $userSetting, $matches)) {
            $includeBookmarked = $matches[1];
        }
        if ($includeBookmarked > 0) {
            $userBookmarkedTorrentIdStr = Bookmark::query()->where('userid', $user->id)->pluck('torrentid')->implode(',');
            if ($includeBookmarked == 1) {
                // only bookmark
                $filters[] = "id IN [$userBookmarkedTorrentIdStr]";
                Logger::writeWithContext((string) "bookmark through inclbookmarked: {$includeBookmarked}", (string) 'info', (bool) false);
            } elseif ($includeBookmarked == 2) {
                // only not bookmark
                $filters[] = "id NOT IN [$userBookmarkedTorrentIdStr]";
                Logger::writeWithContext((string) "bookmark through inclbookmarked: {$includeBookmarked}", (string) 'info', (bool) false);
            }
        }

        $spState = 0;
        if (isset($params['spstate'])) {
            $spState = (int) $params['spstate'];
            Logger::writeWithContext((string) 'spstate from params', (string) 'info', (bool) false);
        } elseif (preg_match("/\[spstate=(\d+)\]/", $userSetting, $matches)) {
            $spState = $matches[1];
            Logger::writeWithContext((string) 'spstate from user setting', (string) 'info', (bool) false);
        }
        // Mirror the SQL path's sp_state logic: only apply the filter when
        // globalSpecialState == 1 (only sp state) or when globalSpecialState
        // matches the requested state (all = that state). Otherwise the SQL
        // path doesn't filter sp_state, so MeiliSearch shouldn't either.
        $globalSpecialState = (int) ($params['global_special_state'] ?? 0);
        if ($spState > 0 && ($globalSpecialState == 1 || $globalSpecialState == $spState)) {
            $filters[] = "sp_state = $spState";
            Logger::writeWithContext((string) "sp_state = {$spState} through spstate: {$spState}", (string) 'info', (bool) false);
        }

        if (isset($params['approval_status']) && is_numeric($params['approval_status'])) {
            $filters[] = 'approval_status = '.(int) $params['approval_status'];
            Logger::writeWithContext((string) "approval_status = {$params['approval_status']} through approval_status: {$params['approval_status']}", (string) 'info', (bool) false);
        }

        // size
        if (! empty($params['size_begin'])) {
            $atomicValue = intval($params['size_begin']) * 1024 * 1024 * 1024;
            $filters[] = "size >= $atomicValue";
            Logger::writeWithContext((string) "size >= {$atomicValue} through size_begin: {$atomicValue}", (string) 'info', (bool) false);
        }
        if (! empty($params['size_end'])) {
            $atomicValue = intval($params['size_end']) * 1024 * 1024 * 1024;
            $filters[] = "size <= $atomicValue";
            Logger::writeWithContext((string) "size <= {$atomicValue} through size_end: {$atomicValue}", (string) 'info', (bool) false);
        }

        // seeders
        if (! empty($params['seeders_begin'])) {
            $atomicValue = intval($params['seeders_begin']);
            $filters[] = "seeders >= $atomicValue";
            Logger::writeWithContext((string) "seeders >= {$atomicValue} through seeders_begin: {$atomicValue}", (string) 'info', (bool) false);
        }
        if (! empty($params['seeders_end'])) {
            $atomicValue = intval($params['seeders_end']);
            $filters[] = "seeders <= $atomicValue";
            Logger::writeWithContext((string) "seeders <= {$atomicValue} through seeders_end: {$atomicValue}", (string) 'info', (bool) false);
        }

        // leechers
        if (! empty($params['leechers_begin'])) {
            $atomicValue = intval($params['leechers_begin']);
            $filters[] = "leechers >= $atomicValue";
            Logger::writeWithContext((string) "leechers >= {$atomicValue} through leechers_begin: {$atomicValue}", (string) 'info', (bool) false);
        }
        if (! empty($params['leechers_end'])) {
            $atomicValue = intval($params['leechers_end']);
            $filters[] = "leechers <= $atomicValue";
            Logger::writeWithContext((string) "leechers <= {$atomicValue} through leechers_end: {$atomicValue}", (string) 'info', (bool) false);
        }

        // times_completed
        if (! empty($params['times_completed_begin'])) {
            $atomicValue = intval($params['times_completed_begin']);
            $filters[] = "times_completed >= $atomicValue";
            Logger::writeWithContext((string) "times_completed >= {$atomicValue} through times_completed_begin: {$atomicValue}", (string) 'info', (bool) false);
        }
        if (! empty($params['times_completed_end'])) {
            $atomicValue = intval($params['times_completed_end']);
            $filters[] = "times_completed <= $atomicValue";
            Logger::writeWithContext((string) "times_completed <= {$atomicValue} through times_completed_end: {$atomicValue}", (string) 'info', (bool) false);
        }

        // added
        if (! empty($params['added_begin'])) {
            $atomicValue = $params['added_begin'];
            $filters[] = 'added >= '.strtotime($atomicValue);
            Logger::writeWithContext((string) "added >= {$atomicValue} through added_begin: {$atomicValue}", (string) 'info', (bool) false);
        }
        if (! empty($params['added_end'])) {
            $atomicValue = Carbon::parse($params['added_end'])->endOfDay()->toDateTimeString();
            $filters[] = 'added <= '.strtotime($atomicValue);
            Logger::writeWithContext((string) "added <= {$atomicValue} through added_end: {$atomicValue}", (string) 'info', (bool) false);
        }

        // permission see banned
        if (isset($params['banned']) && in_array($params['banned'], [1, 0, 'yes', 'no'])) {
            if ($params['banned'] == 1 || $params['banned'] == 'yes') {
                $filters[] = 'banned = 1';
            } else {
                $filters[] = 'banned = 0';
            }
        }

        Logger::writeWithContext((string) ('[GET_FILTERS]: '.json_encode($filters)), (string) 'info', (bool) false);

        return $filters;
    }
}
