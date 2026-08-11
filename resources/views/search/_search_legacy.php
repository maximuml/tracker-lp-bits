<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_SCRIPT_NAME = \App\Support\SupportContext::getServerValue('SCRIPT_NAME');
$search = is_scalar(\App\Support\SupportContext::getRequestInput('search') ?? '') ? (string) (\App\Support\SupportContext::getRequestInput('search') ?? '') : '';
$searchArea = is_scalar(\App\Support\SupportContext::getRequestInput('search_area') ?? '') ? (int) (\App\Support\SupportContext::getRequestInput('search_area') ?? 0) : \App\Repositories\SearchRepository::SEARCH_AREA_TITLE;

//approval status
$approvalStatus = null;
if (!\App\Support\Config\SiteConfig::current()->torrent->approvalStatusNoneVisible() && !\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_APPROVAL)) {
    $approvalStatus = \App\Models\Torrent::APPROVAL_STATUS_ALLOW;
}

//section
$modeArr = [\App\Models\SearchBox::getBrowseMode()];

//see banned
$banned = null;
if (!(isset($CURUSER)) || !\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_VIEW_BANNED)) {
    $banned = "no";
}

$meilisearchEnabled = \App\Support\Config\SiteConfig::current()->meiliSearch->enabled();
$shouldUseMeili = $meilisearchEnabled && !empty($search);

$count = 0;
$rows = [];
if ($search) {
    $searchRaw = $search;
    $search = str_replace(".", " ", $search);
    $searchArr = preg_split("/[\s]+/", $search, 10,PREG_SPLIT_NO_EMPTY);
    if ($shouldUseMeili) {
        try {
            $searchRep = new \App\Repositories\MeiliSearchRepository();
            $searchParams = \App\Support\SupportContext::allQuery();
            $searchParams['search'] = $searchRaw;
            $searchParams['search_area'] = $searchArea;
            if ($approvalStatus != null) {
                $searchParams['approval_status'] = $approvalStatus;
            }
            if ($banned != null) {
                $searchParams['banned'] = $banned;
            }
            //Include dead
            $searchParams['incldead'] = 0;
            $searchParams['mode'] = $modeArr;
            $resultFromSearchRep = $searchRep->search($searchParams, $CURUSER['id']);
            $count = $resultFromSearchRep['total'];
        } catch (\Throwable $e) {
            do_log('MeiliSearch search failed, falling back to SQL: ' . $e->getMessage(), 'error');
            $shouldUseMeili = false;
        }
    }
    if (!$shouldUseMeili) {
        $tableTorrent = "torrents";
        $tableUser = "users";
        $tableCategory = "categories";
        $torrentQuery = \Nexus\Database\NexusDB::table($tableTorrent)
            ->join($tableCategory, "$tableTorrent.category", "=", "$tableCategory.id")
            ->whereIn("$tableCategory.mode", $modeArr)
        ;

        if ($searchArea == \App\Repositories\SearchRepository::SEARCH_AREA_TITLE) {
            foreach ($searchArr as $queryString) {
                $q = "%{$queryString}%";
                $torrentQuery->where(function (\Illuminate\Database\Query\Builder $query) use ($q, $tableTorrent) {
                    return $query->where("$tableTorrent.name", 'like', $q);
                });
            }
        } elseif ($searchArea == \App\Repositories\SearchRepository::SEARCH_AREA_DESC) {
            foreach ($searchArr as $queryString) {
                $q = "%{$queryString}%";
                $torrentQuery->where("$tableTorrent.descr", "like", $q);
            }
        } elseif ($searchArea == \App\Repositories\SearchRepository::SEARCH_AREA_OWNER) {
            $torrentQuery->join($tableUser, "$tableTorrent.owner", "=", "$tableUser.id");
            foreach ($searchArr as $queryString) {
                $q = "%{$queryString}%";
                $torrentQuery->where("$tableUser.username", "like", $q);
            }
        } else {
            foreach ($searchArr as $queryString) {
                $q = "%{$queryString}%";
                $torrentQuery->where("$tableTorrent.name", "like", $q);
            }
            write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is hacking search_area field in" . $__server_SCRIPT_NAME, 'mod');
        }
        if ($approvalStatus !== null) {
            $torrentQuery->where("$tableTorrent.approval_status", $approvalStatus);
        }
        if ($banned !== null) {
            $torrentQuery->where("$tableTorrent.banned", $banned);
        }

        $count = $torrentQuery->count();
    }
}

if ($CURUSER["torrentsperpage"])
    $torrentsperpage = (int)$CURUSER["torrentsperpage"];
elseif ($torrentsperpage_main)
    $torrentsperpage = $torrentsperpage_main;
else $torrentsperpage = 50;

// sorting by MarkoStamcar
$column = 'id';
$ascdesc = 'desc';
$addparam = "?search=$search&search_area=$searchArea&";
if (((\App\Support\SupportContext::getQuery('sort') !== null)) && \App\Support\SupportContext::getQuery('sort') && ((\App\Support\SupportContext::getQuery('type') !== null)) && \App\Support\SupportContext::getQuery('type')) {

    switch(\App\Support\SupportContext::getQuery('sort')) {
        case '1': $column = "name"; break;
        case '2': $column = "numfiles"; break;
        case '3': $column = "comments"; break;
        case '4': $column = "added"; break;
        case '5': $column = "size"; break;
        case '6': $column = "times_completed"; break;
        case '7': $column = "seeders"; break;
        case '8': $column = "leechers"; break;
        case '9': $column = "owner"; break;
        default: $column = "id"; break;
    }

    switch(\App\Support\SupportContext::getQuery('type')) {
        case 'asc': $ascdesc = "ASC"; $linkascdesc = "asc"; break;
        case 'desc': $ascdesc = "DESC"; $linkascdesc = "desc"; break;
        default: $ascdesc = "DESC"; $linkascdesc = "desc"; break;
    }

    $addparam .= "sort=" . intval(\App\Support\SupportContext::getQuery('sort')) . "&type=" . $linkascdesc . "&";

}

list($pagertop, $pagerbottom, $limit, $offset, $size, $page) = pager($torrentsperpage, $count, $addparam);

stdhead(nexus_trans('search.global_search'));
print("<table width=\"97%\" class=\"main\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\">");
if ($search && $count > 0) {
    if ($shouldUseMeili) {
        $rows = $resultFromSearchRep['list'];
    } else {
        $fieldsStr = implode(', ', \App\Models\Torrent::getFieldsForList(true));
        $rows = $torrentQuery->selectRaw("$fieldsStr, categories.mode as search_box_id")
            ->forPage($page + 1, $torrentsperpage)
            ->orderBy("$tableTorrent.$column", $ascdesc)
            ->get()
            ->toArray();
    }
    print($pagertop);
    torrenttable(json_decode(json_encode($rows), true));
    print($pagerbottom);
} else {
    stdmsg($lang_torrents['std_search_results_for'] . htmlspecialchars($search) . "\"",$lang_torrents['std_try_again']);
}
print("</td></tr></table>");
stdfoot();
