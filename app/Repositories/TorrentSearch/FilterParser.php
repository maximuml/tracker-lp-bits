<?php

declare(strict_types=1);

namespace App\Repositories\TorrentSearch;

use App\Auth\Permission;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Log;
use App\Support\Promotion;

/**
 * Parse search query parameters and user notification preferences into
 * structured filter state consumed by the QueryBuilder.
 *
 * Extracted from TorrentSearchRepository (W2-05).
 */
final class FilterParser
{
    /**
     * Parse scalar filters (allsec, bookmarked, dead, banned, spstate) and
     * taxonomy selections (categories, sources, media, codecs, standards,
     * processings, audiocodecs) from the request params or user notifs.
     *
     * @param  array<string, mixed>  $searchParams  Query parameters (mutated copy returned)
     * @param  array<string, mixed>  $CURUSER  Current user legacy array
     * @param  array<int, array<string, mixed>>  $cats
     * @param  array<int, array<string, mixed>>  $sources
     * @param  array<int, array<string, mixed>>  $media
     * @param  array<int, array<string, mixed>>  $codecs
     * @param  array<int, array<string, mixed>>  $standards
     * @param  array<int, array<string, mixed>>  $processings
     * @param  array<int, array<string, mixed>>  $audiocodecs
     * @return array{
     *     wherea: list<string>,
     *     whereBindings: list<mixed>,
     *     whereothera: list<string>,
     *     wherecatina: list<int>,
     *     wheresourceina: list<int>,
     *     wheremediumina: list<int>,
     *     wherecodecina: list<int>,
     *     wherestandardina: list<int>,
     *     whereprocessingina: list<int>,
     *     whereaudiocodecina: list<int>,
     *     addparam: string,
     *     all: bool|int,
     *     inclbookmarked: int,
     *     include_dead: int,
     *     special_state: int,
     *     allsec: int,
     *     searchParams: array<string, mixed>,
     * }
     */
    public function parse(
        array $searchParams,
        array $CURUSER,
        bool $hasSearchParams,
        bool $showsubcat,
        bool $showsource,
        bool $showmedium,
        bool $showcodec,
        bool $showstandard,
        bool $showprocessing,
        bool $showaudiocodec,
        array $cats,
        array $sources,
        array $media,
        array $codecs,
        array $standards,
        array $processings,
        array $audiocodecs,
    ): array {
        $addparam = '';
        $wherea = [];
        $whereBindings = [];
        $wherecatina = [];
        $wheresourceina = [];
        $wheremediumina = [];
        $wherecodecina = [];
        $wherestandardina = [];
        $whereprocessingina = [];
        $whereaudiocodecina = [];
        $whereothera = [];

        // ----------------- start whether show torrents from all sections---------------------//
        if ($hasSearchParams) {
            $allsec = intval($searchParams['allsec'] ?? 0);
        } else {
            $allsec = 0;
        }
        if ($allsec == 1) {		// show torrents from all sections
            $addparam .= 'allsec=1&';
        }
        // ----------------- end whether ignoring section ---------------------//
        // ----------------- start bookmarked ---------------------//
        $inclbookmarked = 0;
        if ($hasSearchParams) {
            $inclbookmarked = intval($searchParams['inclbookmarked'] ?? 0);
        } elseif ($CURUSER['notifs']) {
            $inclbookmarked = $this->matchNotifState((string) $CURUSER['notifs'], 'inclbookmarked', 0, 2) ?? 0;
        }

        if (! in_array($inclbookmarked, [0, 1, 2])) {
            $inclbookmarked = 0;
            Log::writeWithContext('User '.$CURUSER['username'].','.$CURUSER['ip'].' is hacking inclbookmarked field in'.Input::serverValue('SCRIPT_NAME', ''), 'mod');
        }
        if ($inclbookmarked == 0) {  // all(bookmarked,not)
            $addparam .= 'inclbookmarked=0&';
        } elseif ($inclbookmarked == 1) {		// bookmarked
            $addparam .= 'inclbookmarked=1&';
            if (! empty($CURUSER['id'])) {
                $this->pushWhere($wherea, $whereBindings, 'torrents.id IN (SELECT torrentid FROM bookmarks WHERE userid = ?)', [(int) $CURUSER['id']]);
            }
        } elseif ($inclbookmarked == 2) {		// not bookmarked
            $addparam .= 'inclbookmarked=2&';
            if (! empty($CURUSER['id'])) {
                $this->pushWhere($wherea, $whereBindings, 'torrents.id NOT IN (SELECT torrentid FROM bookmarks WHERE userid = ?)', [(int) $CURUSER['id']]);
            }
        }
        // ----------------- end bookmarked ---------------------//

        // ----------------- start include dead ---------------------//
        if (isset($searchParams['incldead'])) {
            $include_dead = intval($searchParams['incldead'] ?? 0);
        } elseif ($CURUSER['notifs']) {
            $include_dead = $this->matchNotifState((string) $CURUSER['notifs'], 'incldead', 0, 2) ?? 1;
        } else {
            $include_dead = 1;
        }

        if (! in_array($include_dead, [0, 1, 2])) {
            $include_dead = 0;
            Log::writeWithContext('User '.$CURUSER['username'].','.$CURUSER['ip'].' is hacking incldead field in'.Input::serverValue('SCRIPT_NAME', ''), 'mod');
        }
        if ($include_dead == 0) {  // all(active,dead)
            $addparam .= 'incldead=0&';
        } elseif ($include_dead == 1) {		// active
            $addparam .= 'incldead=1&';
            $whereothera[] = 'visible = 1';
        } elseif ($include_dead == 2) {		// dead
            $addparam .= 'incldead=2&';
            $whereothera[] = 'visible = 0';
        }
        // ----------------- end include dead ---------------------//

        if (empty($CURUSER['id']) || ! Permission::canViewBannedTorrent()) {
            $whereothera[] = 'banned = 0';
            $searchParams['banned'] = 0;
        }

        $special_state = 0;
        if ($hasSearchParams) {
            $special_state = intval($searchParams['spstate'] ?? 0);
        } elseif ($CURUSER['notifs']) {
            $special_state = $this->matchNotifState((string) $CURUSER['notifs'], 'spstate', 0, 7) ?? 0;
        }

        if (! in_array($special_state, [0, 1, 2, 3, 4, 5, 6, 7])) {
            $special_state = 0;
            Log::writeWithContext('User '.$CURUSER['username'].','.$CURUSER['ip'].' is hacking spstate field in '.Input::serverValue('SCRIPT_NAME', ''), 'mod');
        }
        $globalSpecialState = Promotion::globalSpecialState();
        // Pass globalSpecialState to MeiliSearch so it can apply the same
        // sp_state filtering logic as the SQL path (see getFilters).
        $searchParams['global_special_state'] = $globalSpecialState;
        if ($special_state == 0) {	// all
            $addparam .= 'spstate=0&';
        } elseif ($special_state == 1) {	// normal
            $addparam .= 'spstate=1&';

            $wherea[] = 'sp_state = 1';
        } else {	// 2-7: free, 2x up, 2x up and free, half down, half down, 30% down
            $addparam .= "spstate={$special_state}&";
            if ($globalSpecialState == 1) {	// only sp state
                $wherea[] = "sp_state = {$special_state}";
            } elseif ($globalSpecialState == $special_state) {	// all

            }
        }

        $category_get = intval($searchParams['cat'] ?? 0);
        $source_get = $medium_get = $codec_get = $standard_get = $processing_get = $audiocodec_get = 0;
        if ($showsubcat) {
            if ($showsource) {
                $source_get = intval($searchParams['source'] ?? 0);
            }
            if ($showmedium) {
                $medium_get = intval($searchParams['medium'] ?? 0);
            }
            if ($showcodec) {
                $codec_get = intval($searchParams['codec'] ?? 0);
            }
            if ($showstandard) {
                $standard_get = intval($searchParams['standard'] ?? 0);
            }
            if ($showprocessing) {
                $processing_get = intval($searchParams['processing'] ?? 0);
            }
            if ($showaudiocodec) {
                $audiocodec_get = intval($searchParams['audiocodec'] ?? 0);
            }
        }

        $all = intval($searchParams['all'] ?? 0);

        if (! $all) {
            if (! $hasSearchParams && $CURUSER['notifs']) {
                $all = true;
                $notifs = (string) $CURUSER['notifs'];
                $catResult = $this->collectFromNotifs($cats, 'cat', 'cat', $notifs, $all, $addparam);
                $all = $catResult['all'];
                $wherecatina = $catResult['ina'];
                if ($showsubcat) {
                    if ($showsource) {
                        $res = $this->collectFromNotifs($sources, 'sou', 'source', $notifs, $all, $addparam);
                        $all = $res['all'];
                        $wheresourceina = $res['ina'];
                    }
                    if ($showmedium) {
                        $res = $this->collectFromNotifs($media, 'med', 'medium', $notifs, $all, $addparam);
                        $all = $res['all'];
                        $wheremediumina = $res['ina'];
                    }
                    if ($showcodec) {
                        $res = $this->collectFromNotifs($codecs, 'cod', 'codec', $notifs, $all, $addparam);
                        $all = $res['all'];
                        $wherecodecina = $res['ina'];
                    }
                    if ($showstandard) {
                        $res = $this->collectFromNotifs($standards, 'sta', 'standard', $notifs, $all, $addparam);
                        $all = $res['all'];
                        $wherestandardina = $res['ina'];
                    }
                    if ($showprocessing) {
                        $res = $this->collectFromNotifs($processings, 'pro', 'processing', $notifs, $all, $addparam);
                        $all = $res['all'];
                        $whereprocessingina = $res['ina'];
                    }
                    if ($showaudiocodec) {
                        $res = $this->collectFromNotifs($audiocodecs, 'aud', 'audiocodec', $notifs, $all, $addparam);
                        $all = $res['all'];
                        $whereaudiocodecina = $res['ina'];
                    }
                }
            }
            // when one clicked the cat, source, etc. name/image
            elseif ($category_get) {
                LegacyResponse::assertId($category_get, true, true, true);
                $wherecatina[] = $category_get;
                $addparam .= "cat=$category_get&";
            } elseif ($medium_get) {
                LegacyResponse::assertId($medium_get, true, true, true);
                $wheremediumina[] = $medium_get;
                $addparam .= "medium=$medium_get&";
            } elseif ($source_get) {
                LegacyResponse::assertId($source_get, true, true, true);
                $wheresourceina[] = $source_get;
                $addparam .= "source=$source_get&";
            } elseif ($codec_get) {
                LegacyResponse::assertId($codec_get, true, true, true);
                $wherecodecina[] = $codec_get;
                $addparam .= "codec=$codec_get&";
            } elseif ($standard_get) {
                LegacyResponse::assertId($standard_get, true, true, true);
                $wherestandardina[] = $standard_get;
                $addparam .= "standard=$standard_get&";
            } elseif ($processing_get) {
                LegacyResponse::assertId($processing_get, true, true, true);
                $whereprocessingina[] = $processing_get;
                $addparam .= "processing=$processing_get&";
            } elseif ($audiocodec_get) {
                LegacyResponse::assertId($audiocodec_get, true, true, true);
                $whereaudiocodecina[] = $audiocodec_get;
                $addparam .= "audiocodec=$audiocodec_get&";
            } else { // select and go
                $all = true;
                $catResult = $this->collectFromParams($cats, 'cat', $searchParams, $all, $addparam);
                $all = $catResult['all'];
                $wherecatina = $catResult['ina'];
                if ($showsubcat) {
                    if ($showsource) {
                        $res = $this->collectFromParams($sources, 'source', $searchParams, $all, $addparam);
                        $all = $res['all'];
                        $wheresourceina = $res['ina'];
                    }
                    if ($showmedium) {
                        $res = $this->collectFromParams($media, 'medium', $searchParams, $all, $addparam);
                        $all = $res['all'];
                        $wheremediumina = $res['ina'];
                    }
                    if ($showcodec) {
                        $res = $this->collectFromParams($codecs, 'codec', $searchParams, $all, $addparam);
                        $all = $res['all'];
                        $wherecodecina = $res['ina'];
                    }
                    if ($showstandard) {
                        $res = $this->collectFromParams($standards, 'standard', $searchParams, $all, $addparam);
                        $all = $res['all'];
                        $wherestandardina = $res['ina'];
                    }
                    if ($showprocessing) {
                        $res = $this->collectFromParams($processings, 'processing', $searchParams, $all, $addparam);
                        $all = $res['all'];
                        $whereprocessingina = $res['ina'];
                    }
                    if ($showaudiocodec) {
                        $res = $this->collectFromParams($audiocodecs, 'audiocodec', $searchParams, $all, $addparam);
                        $all = $res['all'];
                        $whereaudiocodecina = $res['ina'];
                    }
                }
            }
        }

        if ($all) {
            $wherecatina = [];
            if ($showsubcat) {
                $wheresourceina = [];
                $wheremediumina = [];
                $wherecodecina = [];
                $wherestandardina = [];
                $whereprocessingina = [];
                $whereaudiocodecina = [];
            }
            $addparam .= '';
        }

        return [
            'wherea' => $wherea,
            'whereBindings' => $whereBindings,
            'whereothera' => $whereothera,
            'wherecatina' => $wherecatina,
            'wheresourceina' => $wheresourceina,
            'wheremediumina' => $wheremediumina,
            'wherecodecina' => $wherecodecina,
            'wherestandardina' => $wherestandardina,
            'whereprocessingina' => $whereprocessingina,
            'whereaudiocodecina' => $whereaudiocodecina,
            'addparam' => $addparam,
            'all' => $all,
            'inclbookmarked' => $inclbookmarked,
            'include_dead' => $include_dead,
            'special_state' => $special_state,
            'allsec' => $allsec,
            'searchParams' => $searchParams,
        ];
    }

    /**
     * Match a `[key=N]` notification preference, scanning N from min to max.
     *
     * @return int|null the matched value, or null when no preference is set
     */
    private function matchNotifState(string $notifs, string $key, int $min, int $max): ?int
    {
        for ($i = $min; $i <= $max; $i++) {
            if (str_contains($notifs, "[$key=$i]")) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Collect taxonomy selections from user notification preferences.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{ina: list<int>, all: bool}
     */
    private function collectFromNotifs(array $items, string $prefix, string $paramKey, string $notifs, bool|int $all, string &$addparam): array
    {
        $ina = [];
        foreach ($items as $item) {
            $findme = '['.$prefix.$item['id'].']';
            $check = strpos($notifs, $findme) !== false;
            $all = $all && $check;
            if ($check) {
                $ina[] = (int) $item['id'];
                $addparam .= "{$paramKey}{$item['id']}=1&";
            }
        }

        return ['ina' => $ina, 'all' => (bool) $all];
    }

    /**
     * Collect taxonomy selections from explicit query parameters.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @param  array<string, mixed>  $searchParams
     * @return array{ina: list<int>, all: int|bool}
     */
    private function collectFromParams(array $items, string $paramKey, array $searchParams, bool|int $all, string &$addparam): array
    {
        $ina = [];
        foreach ($items as $item) {
            $__is = (isset($searchParams[$paramKey.$item['id']]) && $searchParams[$paramKey.$item['id']]);
            $all &= $__is;
            if ($__is) {
                $ina[] = (int) $item['id'];
                $addparam .= "{$paramKey}{$item['id']}=1&";
            }
        }

        return ['ina' => $ina, 'all' => $all];
    }

    /**
     * Append a parameterized where fragment and its bindings.
     *
     * @param  list<string>  $wherea
     * @param  list<mixed>  $whereBindings
     * @param  list<mixed>  $bindings
     */
    private function pushWhere(array &$wherea, array &$whereBindings, string $sql, array $bindings = []): void
    {
        $wherea[] = $sql;
        foreach ($bindings as $binding) {
            $whereBindings[] = $binding;
        }
    }
}
