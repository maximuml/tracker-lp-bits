<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Torrent;
use App\Repositories\TorrentSearch\SearchEngine;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Category;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Logger;
use App\Support\Pagination;
use App\Support\SearchBox;
use App\Support\SearchSuggest;
use App\Support\UserUpdateBatch;
use Illuminate\Support\Facades\DB;

class TorrentSearchRepository
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly TagRepository $tagRepository,
        private readonly SearchEngine $engine,
        private readonly TorrentListingRepository $listingRepository,
        private readonly LegacyRedisCache $cache,
        private readonly UserUpdateBatch $userUpdateBatch,
    ) {}

    /**
     * @param  array<string, mixed>  $query  Query parameters to use instead of $_GET
     * @return array<string, mixed>
     */
    public function getListingData(array $query, string $script): array
    {
        $CURUSER = $this->currentUser->get() ?? [];
        $browsecatmode = SiteConfig::current()->main->browseCat(1);
        $torrentsperpage_main = SiteConfig::current()->main->torrentsPerPage();
        $catimgurl = '';
        $catpadding = 0;
        $catsperrow = 0;

        if (empty($CURUSER)) {
            $CURUSER = [];
        }

        $sources = $media = $codecs = $standards = $processings = $audiocodecs = [];

        // check searchbox
        switch ($script) {
            case 'torrents':
                $sectiontype = $browsecatmode;
                break;
            default:
                $sectiontype = 0;
        }
        /**
         * tags
         */
        $tagRep = $this->tagRepository;
        $allTags = $tagRep->listAll($sectiontype);
        $filterInputWidth = 62;
        $searchParams = $query ?: request()->query();
        $hasSearchParams = ! empty($searchParams);
        $filterInput = $searchParams;
        $searchParams['mode'] = $sectiontype;

        $showsubcat = (int) SearchBox::valueWithContext($sectiontype, 'showsubcat'); // whether show subcategory (i.e. sources, codecs) or not
        $showsource = (int) SearchBox::valueWithContext($sectiontype, 'showsource'); // whether show sources or not
        $showmedium = (int) SearchBox::valueWithContext($sectiontype, 'showmedium'); // whether show media or not
        $showcodec = (int) SearchBox::valueWithContext($sectiontype, 'showcodec'); // whether show codecs or not
        $showstandard = (int) SearchBox::valueWithContext($sectiontype, 'showstandard'); // whether show standards or not
        $showprocessing = (int) SearchBox::valueWithContext($sectiontype, 'showprocessing'); // whether show processings or not
        $showaudiocodec = (int) SearchBox::valueWithContext($sectiontype, 'showaudiocodec'); // whether show audio codec or not
        $catsperrow = SearchBox::valueWithContext($sectiontype, 'catsperrow'); // show how many cats per line in search box
        $catpadding = SearchBox::valueWithContext($sectiontype, 'catpadding'); // padding space between categories in pixel

        $cats = Category::listByModeWithContext($sectiontype);
        if ($showsubcat) {
            if ($showsource) {
                $sources = SearchBox::itemListWithContext('sources', $sectiontype);
            }
            if ($showmedium) {
                $media = SearchBox::itemListWithContext('media', $sectiontype);
            }
            if ($showcodec) {
                $codecs = SearchBox::itemListWithContext('codecs', $sectiontype);
            }
            if ($showstandard) {
                $standards = SearchBox::itemListWithContext('standards', $sectiontype);
            }
            if ($showprocessing) {
                $processings = SearchBox::itemListWithContext('processings', $sectiontype);
            }
            if ($showaudiocodec) {
                $audiocodecs = SearchBox::itemListWithContext('audiocodecs', $sectiontype);
            }
        }

        $searchstr_raw = is_scalar($searchParams['search'] ?? '') ? (string) ($searchParams['search'] ?? '') : '';
        $searchstr_ori = htmlspecialchars(trim($searchstr_raw));
        $searchstr = substr(DB::getPdo()->quote(trim($searchstr_raw)), 1, -1);
        $searchParams['search'] = $searchstr_raw;
        if (empty($searchstr)) {
            $searchstr = null;
        }

        $meilisearchEnabled = SiteConfig::current()->meiliSearch->enabled();
        $shouldUseMeili = $meilisearchEnabled && ! empty($searchstr);
        Logger::writeWithContext((string) "[SHOULD_USE_MEILI]: {$shouldUseMeili}", (string) 'info', (bool) false);
        // sorting by MarkoStamcar
        $allCategoryId = \App\Models\SearchBox::listCategoryId($sectiontype);

        $sorting = $this->engine->sortingBuilder->build($searchParams);
        $column = $sorting['column'];
        $ascdesc = $sorting['ascdesc'];
        $linkascdesc = $sorting['linkascdesc'];
        $orderBy = $sorting['orderBy'];
        $pagerlink = $sorting['pagerlink'];

        $filters = $this->engine->filterParser->parse(
            $searchParams,
            $CURUSER,
            $hasSearchParams,
            $showsubcat,
            $showsource,
            $showmedium,
            $showcodec,
            $showstandard,
            $showprocessing,
            $showaudiocodec,
            $cats,
            $sources,
            $media,
            $codecs,
            $standards,
            $processings,
            $audiocodecs,
        );
        $wherea = $filters['wherea'];
        $whereBindings = $filters['whereBindings'];
        $whereothera = $filters['whereothera'];
        $wherecatina = $filters['wherecatina'];
        $wheresourceina = $filters['wheresourceina'];
        $wheremediumina = $filters['wheremediumina'];
        $wherecodecina = $filters['wherecodecina'];
        $wherestandardina = $filters['wherestandardina'];
        $whereprocessingina = $filters['whereprocessingina'];
        $whereaudiocodecina = $filters['whereaudiocodecina'];
        $addparam = $filters['addparam'];
        $all = $filters['all'];
        $inclbookmarked = $filters['inclbookmarked'];
        $include_dead = $filters['include_dead'];
        $special_state = $filters['special_state'];
        $allsec = $filters['allsec'];
        $searchParams = $filters['searchParams'];

        $built = $this->engine->queryBuilder->buildWhere(
            $searchParams,
            $CURUSER,
            $wherea,
            $whereBindings,
            $whereothera,
            $wherecatina,
            $wheresourceina,
            $wheremediumina,
            $wherecodecina,
            $wherestandardina,
            $whereprocessingina,
            $whereaudiocodecina,
            $addparam,
            $showsubcat,
            $showsource,
            $showmedium,
            $showcodec,
            $showstandard,
            $showprocessing,
            $showaudiocodec,
            $allCategoryId,
            $inclbookmarked,
            $allsec,
            $searchstr,
            $searchstr_raw,
            $column,
        );
        $where = $built['where'];
        $whereBindings = $built['where_bindings'];
        $listingOptions = $built['listingOptions'];
        $search_area = $built['search_area'];
        $addparam = $built['addparam'];
        $approvalStatus = $built['approvalStatus'];
        $showApprovalStatusFilter = $built['showApprovalStatusFilter'];
        $tagId = $built['tagId'];
        $searchParams = $built['searchParams'];

        if ($shouldUseMeili) {
            try {
                $resultFromSearchRep = $this->engine->meiliAdapter->search($searchParams, $this->currentUser->id());
                $count = $resultFromSearchRep['total'];
            } catch (\Throwable $e) {
                Logger::writeWithContext((string) ('MeiliSearch search failed, falling back to SQL: '.$e->getMessage()), (string) 'error', (bool) false);
                $shouldUseMeili = false;
                $count = $this->engine->sqlFallback->getCount($listingOptions);
            }
        } else {
            $count = $this->engine->sqlFallback->getCount($listingOptions);
        }
        $maxPageSize = 100;
        if (! empty($searchParams['pageSize'])) {
            $torrentsperpage = (int) $searchParams['pageSize'];
        } elseif ($this->currentUser->value('torrentsperpage')) {
            $torrentsperpage = (int) $this->currentUser->value('torrentsperpage');
        } elseif ($torrentsperpage_main) {
            $torrentsperpage = $torrentsperpage_main;
        } else {
            $torrentsperpage = $maxPageSize;
        }
        $torrentsperpage = min($maxPageSize, $torrentsperpage);

        if ($count) {
            if ($searchstr !== null && (! isset($searchParams['notnewword']) || ! $searchParams['notnewword'])) {
                SearchSuggest::add((string) $searchstr, $this->currentUser->id(), (bool) true);
            }
            if ($pagerlink !== '') {
                if (substr($addparam, -1) === ';') {
                    $addparam .= $pagerlink;
                } else {
                    $addparam .= '&'.$pagerlink;
                }
            }

            [$pagertop, $pagerbottom, $limit, $offset, $size, $page] = Pagination::pager($torrentsperpage, $count, '?'.$addparam);

            $fieldsArr = Torrent::getFieldsForList(true);
            $rows = $shouldUseMeili
                ? $resultFromSearchRep['list']
                : $this->engine->sqlFallback->getList(array_merge($listingOptions, [
                    'fields' => $fieldsArr,
                    'search_box_id' => $sectiontype,
                    'order_by' => $orderBy,
                    'offset' => $offset,
                    'limit' => $size,
                ]));
        }

        if ($searchstr !== null) {
            $pageTitle = __('torrents.head_search_results_for').$searchstr_ori;
        } elseif ($sectiontype == $browsecatmode) {
            $pageTitle = __('torrents.head_torrents');
        } else {
            $pageTitle = __('torrents.head_special');
        }

        $hotSearches = $this->hotSearchKeywords();
        $emptyTitle = '';
        $emptyBody = '';
        if (! $count) {
            if (isset($searchstr)) {
                $emptyTitle = __('torrents.std_search_results_for').$searchstr_ori.'"';
                $emptyBody = __('torrents.std_try_again');
            } else {
                $emptyTitle = __('torrents.std_nothing_found');
                $emptyBody = __('torrents.std_no_active_torrents');
            }
        }
        if ($CURUSER !== []) {
            $this->userUpdateBatch->add($sectiontype == $browsecatmode ? 'last_browse' : 'last_music', TIMENOW);
        }

        return get_defined_vars();
    }

    /**
     * Hot-search keywords for the torrents panel. Cached as a plain list
     * (Variant A: markup is owned by the Blade template, not the repo).
     *
     * @return list<string>
     */
    private function hotSearchKeywords(): array
    {
        $cached = $this->cache->get_value('hot_search_keywords');
        if (is_array($cached)) {
            return array_values(array_map('strval', $cached));
        }
        $this->listingRepository->cleanupSuggest();
        $keywords = [];
        $hotcount = 0;
        foreach ($this->listingRepository->getHotSearch() as $searchrow) {
            $keyword = (string) ($searchrow['keywords'] ?? '');
            if ($keyword === '') {
                continue;
            }
            $keywords[] = $keyword;
            $hotcount += mb_strlen($keyword, 'UTF-8');
            if ($hotcount > 60) {
                break;
            }
        }
        $this->cache->cache_value('hot_search_keywords', $keywords, 3670);

        return $keywords;
    }
}
