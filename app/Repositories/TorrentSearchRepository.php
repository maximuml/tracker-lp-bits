<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Torrent;
use App\Repositories\TorrentSearch\FilterParser;
use App\Repositories\TorrentSearch\MeiliAdapter;
use App\Repositories\TorrentSearch\QueryBuilder;
use App\Repositories\TorrentSearch\SqlFallback;
use App\Support\Category;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Logger;
use App\Support\Pagination;
use App\Support\RequestContext;
use App\Support\SearchBox;
use App\Support\SearchSuggest;
use Illuminate\Support\Facades\DB;

class TorrentSearchRepository
{
    /**
     * @param  array<string, mixed>  $query  Query parameters to use instead of $_GET
     * @return array<string, mixed>
     */
    public function getListingData(array $query = []): array
    {
        $CURUSER = app(CurrentUser::class)->get() ?? [];
        $lang_torrents = app(Globals::class)->get('lang_torrents', []);
        $browsecatmode = (int) app(Globals::class)->get('browsecatmode', 1);
        $torrentsperpage_main = (int) app(Globals::class)->get('torrentsperpage_main', 0);
        $catimgurl = '';
        $catpadding = 0;
        $catsperrow = 0;

        if (empty($CURUSER)) {
            $CURUSER = [];
        }

        $sources = $media = $codecs = $standards = $processings = $audiocodecs = [];

        // check searchbox
        switch (RequestContext::instance()->getScript()) {
            case 'torrents':
                $sectiontype = $browsecatmode;
                break;
            default:
                $sectiontype = 0;
        }
        /**
         * tags
         */
        $tagRep = app(TagRepository::class);
        $allTags = $tagRep->listAll($sectiontype);
        $filterInputWidth = 62;
        $searchParams = $query ?: request()->query();
        $hasSearchParams = ! empty($searchParams);
        $searchParams['mode'] = $sectiontype;

        $showsubcat = SearchBox::valueWithContext($sectiontype, 'showsubcat'); // whether show subcategory (i.e. sources, codecs) or not
        $showsource = SearchBox::valueWithContext($sectiontype, 'showsource'); // whether show sources or not
        $showmedium = SearchBox::valueWithContext($sectiontype, 'showmedium'); // whether show media or not
        $showcodec = SearchBox::valueWithContext($sectiontype, 'showcodec'); // whether show codecs or not
        $showstandard = SearchBox::valueWithContext($sectiontype, 'showstandard'); // whether show standards or not
        $showprocessing = SearchBox::valueWithContext($sectiontype, 'showprocessing'); // whether show processings or not
        $showaudiocodec = SearchBox::valueWithContext($sectiontype, 'showaudiocodec'); // whether show audio codec or not
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

        $sorting = app(QueryBuilder::class)->buildSorting($searchParams);
        $column = $sorting['column'];
        $ascdesc = $sorting['ascdesc'];
        $linkascdesc = $sorting['linkascdesc'];
        $orderBy = $sorting['orderBy'];
        $pagerlink = $sorting['pagerlink'];

        $filters = app(FilterParser::class)->parse(
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

        $built = app(QueryBuilder::class)->buildWhere(
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
                $resultFromSearchRep = app(MeiliAdapter::class)->search($searchParams, $CURUSER['id']);
                $count = $resultFromSearchRep['total'];
            } catch (\Throwable $e) {
                Logger::writeWithContext((string) ('MeiliSearch search failed, falling back to SQL: '.$e->getMessage()), (string) 'error', (bool) false);
                $shouldUseMeili = false;
                $count = app(SqlFallback::class)->getCount($listingOptions);
            }
        } else {
            $count = app(SqlFallback::class)->getCount($listingOptions);
        }
        $maxPageSize = 100;
        if (! empty($searchParams['pageSize'])) {
            $torrentsperpage = (int) $searchParams['pageSize'];
        } elseif ($CURUSER['torrentsperpage']) {
            $torrentsperpage = (int) $CURUSER['torrentsperpage'];
        } elseif ($torrentsperpage_main) {
            $torrentsperpage = $torrentsperpage_main;
        } else {
            $torrentsperpage = $maxPageSize;
        }
        $torrentsperpage = min($maxPageSize, $torrentsperpage);

        if ($count) {
            if ($searchstr !== null && (! isset($searchParams['notnewword']) || ! $searchParams['notnewword'])) {
                SearchSuggest::add((string) $searchstr, $CURUSER['id'], (bool) true);
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
                : app(SqlFallback::class)->getList(array_merge($listingOptions, [
                    'fields' => $fieldsArr,
                    'search_box_id' => $sectiontype,
                    'order_by' => $orderBy,
                    'offset' => $offset,
                    'limit' => $size,
                ]));
        }

        if ($searchstr !== null) {
            $pageTitle = $lang_torrents['head_search_results_for'].$searchstr_ori;
        } elseif ($sectiontype == $browsecatmode) {
            $pageTitle = $lang_torrents['head_torrents'];
        } else {
            $pageTitle = $lang_torrents['head_special'];
        }

        return get_defined_vars();
    }
}
