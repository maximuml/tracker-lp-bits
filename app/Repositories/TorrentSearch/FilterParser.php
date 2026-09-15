<?php

declare(strict_types=1);

namespace App\Repositories\TorrentSearch;

use App\Auth\Permission;
use App\Support\Input;
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
    public function __construct(
        private readonly TaxonomySelectionParser $taxonomySelection = new TaxonomySelectionParser,
    ) {}

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
        int|bool $showsubcat,
        int|bool $showsource,
        int|bool $showmedium,
        int|bool $showcodec,
        int|bool $showstandard,
        int|bool $showprocessing,
        int|bool $showaudiocodec,
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

        $taxonomy = $this->taxonomySelection->resolve(
            $searchParams, $CURUSER, $hasSearchParams,
            $showsubcat, $showsource, $showmedium, $showcodec, $showstandard, $showprocessing, $showaudiocodec,
            $cats, $sources, $media, $codecs, $standards, $processings, $audiocodecs,
        );
        $wherecatina = $taxonomy['wherecatina'];
        $wheresourceina = $taxonomy['wheresourceina'];
        $wheremediumina = $taxonomy['wheremediumina'];
        $wherecodecina = $taxonomy['wherecodecina'];
        $wherestandardina = $taxonomy['wherestandardina'];
        $whereprocessingina = $taxonomy['whereprocessingina'];
        $whereaudiocodecina = $taxonomy['whereaudiocodecina'];
        $addparam .= $taxonomy['addparam'];
        $all = $taxonomy['all'];

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
