<?php

declare(strict_types=1);

namespace App\Repositories\TorrentSearch;

use App\Auth\Permission;
use App\Enums\TorrentApprovalStatus;
use App\Support\Config\SiteConfig;
use App\Support\Input;
use App\Support\Log;
use Carbon\Carbon;

/**
 * Build SQL where clauses, sort order, and listing options from parsed
 * filter state and search parameters.
 *
 * Extracted from TorrentSearchRepository (W2-05).
 */
final class QueryBuilder
{
    /**
     * Build the sort order and pager link from the sort/type search params.
     *
     * @param  array<string, mixed>  $searchParams
     * @return array{column: string, ascdesc: string, linkascdesc: string, orderBy: array<int, array{0: string, 1: string}>, pagerlink: string}
     */
    public function buildSorting(array $searchParams): array
    {
        $column = '';
        $ascdesc = '';
        $linkascdesc = '';
        if (isset($searchParams['sort']) && $searchParams['sort'] && isset($searchParams['type']) && $searchParams['type']) {

            switch ($searchParams['sort']) {
                case '1': $column = 'name';
                    break;
                case '2': $column = 'numfiles';
                    break;
                case '3': $column = 'comments';
                    break;
                case '4': $column = 'added';
                    break;
                case '5': $column = 'size';
                    break;
                case '6': $column = 'times_completed';
                    break;
                case '7': $column = 'seeders';
                    break;
                case '8': $column = 'leechers';
                    break;
                case '9': $column = 'owner';
                    break;
                default: $column = 'id';
                    break;
            }

            switch ($searchParams['type']) {
                case 'asc': $ascdesc = 'ASC';
                    $linkascdesc = 'asc';
                    break;
                case 'desc': $ascdesc = 'DESC';
                    $linkascdesc = 'desc';
                    break;
                default: $ascdesc = 'DESC';
                    $linkascdesc = 'desc';
                    break;
            }

            if ($column == 'owner') {
                $orderBy = [
                    ['pos_state', 'desc'],
                    ['torrents.anonymous', 'asc'],
                    ['users.username', $ascdesc],
                ];
            } else {
                $orderBy = [
                    ['pos_state', 'desc'],
                    ['torrents.'.$column, $ascdesc],
                ];
            }

            $pagerlink = 'sort='.intval($searchParams['sort']).'&type='.$linkascdesc.'&';

        } else {

            $orderBy = [
                ['pos_state', 'desc'],
                ['torrents.id', 'desc'],
            ];
            $pagerlink = '';

        }

        return [
            'column' => $column,
            'ascdesc' => $ascdesc,
            'linkascdesc' => $linkascdesc,
            'orderBy' => $orderBy,
            'pagerlink' => $pagerlink,
        ];
    }

    /**
     * Assemble the final SQL where string, bindings, and listing options.
     *
     * @param  array<string, mixed>  $searchParams
     * @param  array<string, mixed>  $CURUSER
     * @param  list<string>  $wherea
     * @param  list<mixed>  $whereBindings
     * @param  list<string>  $whereothera
     * @param  list<int>  $wherecatina
     * @param  list<int>  $wheresourceina
     * @param  list<int>  $wheremediumina
     * @param  list<int>  $wherecodecina
     * @param  list<int>  $wherestandardina
     * @param  list<int>  $whereprocessingina
     * @param  list<int>  $whereaudiocodecina
     * @param  array<int|string, mixed>|string|null  $allCategoryId
     * @return array{
     *     where: string,
     *     where_bindings: list<mixed>,
     *     listingOptions: array<string, mixed>,
     *     search_area: int,
     *     addparam: string,
     *     wherebase: list<string>,
     *     approvalStatus: int|null,
     *     showApprovalStatusFilter: bool,
     *     tagId: int,
     *     searchParams: array<string, mixed>,
     * }
     */
    public function buildWhere(
        array $searchParams,
        array $CURUSER,
        array $wherea,
        array $whereBindings,
        array $whereothera,
        array $wherecatina,
        array $wheresourceina,
        array $wheremediumina,
        array $wherecodecina,
        array $wherestandardina,
        array $whereprocessingina,
        array $whereaudiocodecina,
        string $addparam,
        int|bool $showsubcat,
        int|bool $showsource,
        int|bool $showmedium,
        int|bool $showcodec,
        int|bool $showstandard,
        int|bool $showprocessing,
        int|bool $showaudiocodec,
        array|string|null $allCategoryId,
        int $inclbookmarked,
        int $allsec,
        ?string $searchstr,
        string $searchstr_raw,
        string $column,
    ): array {
        $wherecatin = $wheresourcein = $wheremediumin = $wherecodecin = $wherestandardin = $whereprocessingin = $whereaudiocodecin = '';
        if (empty($wherecatina) && ! (in_array($inclbookmarked, [1, 2]) && $allsec == 1)) {
            // require limit in some category
            $wherecatina = $allCategoryId;
        }
        $wherecatina = is_array($wherecatina) ? $wherecatina : [];

        if (count($wherecatina) > 1) {
            $wherecatin = implode(',', $wherecatina);
        } elseif (count($wherecatina) == 1) {
            $wherea[] = "category = $wherecatina[0]";
        }

        if ($showsubcat) {
            if ($showsource) {
                $clause = $this->buildInClause($wheresourceina);
                $wheresourcein = $clause['in'];
                if ($clause['single'] !== null) {
                    $wherea[] = "source = $clause[single]";
                }
            }

            if ($showmedium) {
                $clause = $this->buildInClause($wheremediumina);
                $wheremediumin = $clause['in'];
                if ($clause['single'] !== null) {
                    $wherea[] = "medium = $clause[single]";
                }
            }

            if ($showcodec) {
                $clause = $this->buildInClause($wherecodecina);
                $wherecodecin = $clause['in'];
                if ($clause['single'] !== null) {
                    $wherea[] = "codec = $clause[single]";
                }
            }

            if ($showstandard) {
                $clause = $this->buildInClause($wherestandardina);
                $wherestandardin = $clause['in'];
                if ($clause['single'] !== null) {
                    $wherea[] = "standard = $clause[single]";
                }
            }

            if ($showprocessing) {
                $clause = $this->buildInClause($whereprocessingina);
                $whereprocessingin = $clause['in'];
                if ($clause['single'] !== null) {
                    $wherea[] = "processing = $clause[single]";
                }
            }
        }

        if ($showaudiocodec) {
            $clause = $this->buildInClause($whereaudiocodecina);
            $whereaudiocodecin = $clause['in'];
            if ($clause['single'] !== null) {
                $wherea[] = "audiocodec = $clause[single]";
            }
        }

        $wherebase = $wherea;
        $search_area = 0;
        if ($searchstr !== null) {
            if (! isset($searchParams['notnewword']) || ! $searchParams['notnewword']) {
                $notnewword = '';
            } else {
                $notnewword = 'notnewword=1&';
            }
            $search_mode = intval($searchParams['search_mode'] ?? 0);
            /**
             * Deprecated search mode: 1(OR)
             *
             * @since 1.8
             */
            if (! in_array($search_mode, [0, 2])) {
                $search_mode = 0;
                Log::writeWithContext('User '.$CURUSER['username'].','.$CURUSER['ip'].' is hacking search_mode field in'.Input::serverValue('SCRIPT_NAME', ''), 'mod');
            }

            $search_area = intval($searchParams['search_area'] ?? 0);

            $likePatterns = [];
            $searchTerm = trim($searchstr_raw);
            switch ($search_mode) {
                case 0:	// AND, OR
                case 1:

                    $searchTerm = str_replace('.', ' ', $searchTerm);
                    $searchstr_exploded = explode(' ', $searchTerm);
                    $searchstr_exploded_count = 0;
                    foreach ($searchstr_exploded as $searchstr_element) {
                        $searchstr_element = trim($searchstr_element);
                        if ($searchstr_element === '') {
                            continue;
                        }
                        $searchstr_exploded_count++;
                        if ($searchstr_exploded_count > 3) {    // maximum 3 keywords
                            break;
                        }
                        $likePatterns[] = '%'.$searchstr_element.'%';
                    }
                    break;

                case 2:	// exact

                    $likePatterns[] = '%'.$searchTerm.'%';
                    break;
            }
            $ANDOR = ($search_mode == 0 ? ' AND ' : ' OR ');	// only affects mode 0 and mode 1

            $searchColumn = match ($search_area) {
                1 => 'torrent_extras.descr',
                3 => 'users.username',
                0 => 'torrents.name',
                default => 'torrents.name',
            };

            if ($search_area === 3) {
                $likeClauses = array_fill(0, count($likePatterns), $searchColumn.' LIKE ?');
                $likeSql = implode($ANDOR, $likeClauses);

                if (empty($CURUSER['id'])) {
                    // not registered user, only show not anonymous torrents
                    $this->pushWhere($wherea, $whereBindings, $likeSql.' AND torrents.anonymous = 0', $likePatterns);
                } elseif (Permission::canManageTorrent()) {
                    // moderator or above, show all
                    $this->pushWhere($wherea, $whereBindings, $likeSql, $likePatterns);
                } else {
                    // only show normal torrents and anonymous torrents from himself
                    $sql = "({$likeSql} AND torrents.anonymous = 0) OR ({$likeSql} AND torrents.anonymous = 1 AND users.id = ?)";
                    $this->pushWhere($wherea, $whereBindings, $sql, array_merge($likePatterns, $likePatterns, [(int) $CURUSER['id']]));
                }
            } else {
                if (empty($likePatterns)) {
                    $likePatterns[] = '%'.$searchTerm.'%';
                }
                $likeClauses = array_fill(0, count($likePatterns), $searchColumn.' LIKE ?');

                if ($search_area !== 0 && $search_area !== 1) {
                    $search_area = 0;
                    Log::writeWithContext('User '.$CURUSER['username'].','.$CURUSER['ip'].' is hacking search_area field in'.Input::serverValue('SCRIPT_NAME', ''), 'mod');
                }

                $this->pushWhere($wherea, $whereBindings, '('.implode($ANDOR, $likeClauses).')', $likePatterns);
            }

            $addparam .= 'search_area='.$search_area.'&';
            $addparam .= 'search='.rawurlencode($searchstr).'&'.$notnewword;
            $addparam .= 'search_mode='.$search_mode.'&';
        }

        // approval status
        $approvalStatusNoneVisible = SiteConfig::current()->torrent->approvalStatusNoneVisible();
        $approvalStatusIconEnabled = SiteConfig::current()->torrent->approvalStatusIconEnabled();
        $approvalStatus = null;
        $showApprovalStatusFilter = false;
        // when enable approval status icon, all user can use this filter, otherwise only staff member and approval none visible is 'no' can use
        if ($approvalStatusIconEnabled || (Permission::canApproveTorrent() && ! $approvalStatusNoneVisible)) {
            $showApprovalStatusFilter = true;
        }
        // when user can use approval status filter, and pass `approval_status` parameter, will affect
        // OR if [not approval can not be view] and not staff member, force to view  approval allowed
        if ($showApprovalStatusFilter && isset($searchParams['approval_status']) && is_numeric($searchParams['approval_status'])) {
            $approvalStatus = intval($searchParams['approval_status']);
            $this->pushWhere($wherea, $whereBindings, 'torrents.approval_status = ?', [(int) $approvalStatus]);
            $searchParams['approval_status'] = $approvalStatus;
            $addparam .= "approval_status=$approvalStatus&";
        } elseif (! $approvalStatusNoneVisible && ! Permission::canApproveTorrent()) {
            $this->pushWhere($wherea, $whereBindings, 'torrents.approval_status = ?', [(int) TorrentApprovalStatus::ALLOW->value]);
            $searchParams['approval_status'] = TorrentApprovalStatus::ALLOW->value;
        }

        if (isset($searchParams['size_begin']) && ctype_digit($searchParams['size_begin'])) {
            $this->pushWhere($wherea, $whereBindings, 'torrents.size >= ?', [intval($searchParams['size_begin']) * 1024 * 1024 * 1024]);
            $addparam .= 'size_begin='.intval($searchParams['size_begin']).'&';
        }
        if (isset($searchParams['size_end']) && ctype_digit($searchParams['size_end'])) {
            $this->pushWhere($wherea, $whereBindings, 'torrents.size <= ?', [intval($searchParams['size_end']) * 1024 * 1024 * 1024]);
            $addparam .= 'size_end='.intval($searchParams['size_end']).'&';
        }

        $this->addNumericRange($wherea, $whereBindings, $searchParams, $addparam, 'seeders');
        $this->addNumericRange($wherea, $whereBindings, $searchParams, $addparam, 'leechers');
        $this->addNumericRange($wherea, $whereBindings, $searchParams, $addparam, 'times_completed');

        if (isset($searchParams['added_begin']) && ! empty($searchParams['added_begin'])) {
            $this->pushWhere($wherea, $whereBindings, 'torrents.added >= ?', [(string) $searchParams['added_begin']]);
            $addparam .= 'added_begin='.$searchParams['added_begin'].'&';
        }
        if (isset($searchParams['added_end']) && ! empty($searchParams['added_end'])) {
            $this->pushWhere($wherea, $whereBindings, 'torrents.added <= ?', [Carbon::parse($searchParams['added_end'])->endOfDay()->toDateTimeString()]);
            $addparam .= 'added_end='.$searchParams['added_end'].'&';
        }

        $where = implode(' AND ', $wherea);

        if ($wherecatin) {
            $where .= ($where ? ' AND ' : '').'category IN('.$wherecatin.')';
        }
        if ($showsubcat) {
            if ($wheresourcein) {
                $where .= ($where ? ' AND ' : '').'source IN('.$wheresourcein.')';
            }
            if ($wheremediumin) {
                $where .= ($where ? ' AND ' : '').'medium IN('.$wheremediumin.')';
            }
            if ($wherecodecin) {
                $where .= ($where ? ' AND ' : '').'codec IN('.$wherecodecin.')';
            }
            if ($wherestandardin) {
                $where .= ($where ? ' AND ' : '').'standard IN('.$wherestandardin.')';
            }
            if ($whereprocessingin) {
                $where .= ($where ? ' AND ' : '').'processing IN('.$whereprocessingin.')';
            }
            if ($whereaudiocodecin) {
                $where .= ($where ? ' AND ' : '').'audiocodec IN('.$whereaudiocodecin.')';
            }
        }
        // last
        if (! empty($whereothera)) {
            $where .= ($where ? ' AND ' : '').implode(' AND ', $whereothera);
        }

        $tagId = intval($searchParams['tag_id'] ?? 0);
        if ($tagId > 0) {
            $addparam .= "tag_id={$tagId}&";
        }
        $listingOptions = [
            'where' => $where,
            'where_bindings' => $whereBindings,
            'join_users' => ($search_area == 3 || $column == 'owner'),
            'join_torrent_tags' => $tagId > 0,
            'tag_id' => $tagId,
            'join_torrent_extras' => $search_area == 1,
        ];

        return [
            'where' => $where,
            'where_bindings' => $whereBindings,
            'listingOptions' => $listingOptions,
            'search_area' => $search_area,
            'addparam' => $addparam,
            'wherebase' => $wherebase,
            'approvalStatus' => $approvalStatus,
            'showApprovalStatusFilter' => $showApprovalStatusFilter,
            'tagId' => $tagId,
            'searchParams' => $searchParams,
        ];
    }

    /**
     * Build an IN-clause string or a single equality fragment.
     *
     * @param  list<int>  $ina
     * @return array{in: string, single: ?string}
     */
    private function buildInClause(array $ina): array
    {
        if (count($ina) > 1) {
            return ['in' => implode(',', $ina), 'single' => null];
        }
        if (count($ina) == 1) {
            return ['in' => '', 'single' => (string) $ina[0]];
        }

        return ['in' => '', 'single' => null];
    }

    /**
     * Add begin/end numeric range filters (seeders, leechers, times_completed).
     *
     * @param  list<string>  $wherea
     * @param  list<mixed>  $whereBindings
     * @param  array<string, mixed>  $searchParams
     */
    private function addNumericRange(array &$wherea, array &$whereBindings, array $searchParams, string &$addparam, string $field): void
    {
        if (isset($searchParams[$field.'_begin']) && ctype_digit($searchParams[$field.'_begin'])) {
            $this->pushWhere($wherea, $whereBindings, "torrents.{$field} >= ?", [(int) $searchParams[$field.'_begin']]);
            $addparam .= $field.'_begin='.intval($searchParams[$field.'_begin']).'&';
        }
        if (isset($searchParams[$field.'_end']) && ctype_digit($searchParams[$field.'_end'])) {
            $this->pushWhere($wherea, $whereBindings, "torrents.{$field} <= ?", [(int) $searchParams[$field.'_end']]);
            $addparam .= $field.'_end='.intval($searchParams[$field.'_end']).'&';
        }
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
