<?php
require_once("../include/bittorrent.php");
dbconn(true);
require_once(get_langfile_path('torrents.php'));
loggedinorreturn();
parked();

$sectiontype = $browsecatmode;
if (
    get_setting('main.spsct') == 'yes'
    && user_can('view_special_torrent')
    && ($_GET['section'] ?? '') === 'special'
) {
    $sectiontype = $specialcatmode;
}

$view = $_GET['view'] ?? 'card';
if (!in_array($view, \App\Support\TorrentGrid::VIEWS, true)) {
    $view = 'card';
}

$addparam = 'view=' . urlencode($view) . '&';

$db = \Nexus\Database\NexusDB::getInstance();
$wherea = ['torrents.visible = "yes"'];

$genreList = genrelist($sectiontype);
$allowedCategoryIds = array_map('intval', array_column($genreList, 'id'));
if ($allowedCategoryIds !== []) {
    $wherea[] = 'torrents.category IN (' . implode(',', $allowedCategoryIds) . ')';
}

if (!user_can('seebanned')) {
    $wherea[] = 'torrents.banned = "no"';
}

$approvalStatusNoneVisible = get_setting('torrent.approval_status_none_visible');
if ($approvalStatusNoneVisible == 'no' && !user_can('torrent-approval')) {
    $wherea[] = 'torrents.approval_status = ' . \App\Models\Torrent::APPROVAL_STATUS_ALLOW;
}

$catId = intval($_GET['cat'] ?? 0);
if ($catId > 0 && in_array($catId, $allowedCategoryIds, true)) {
    $wherea[] = 'torrents.category = ' . $catId;
    $addparam .= 'cat=' . $catId . '&';
} else {
    $catId = 0;
}

$tagId = intval($_GET['tag'] ?? 0);
if ($tagId > 0) {
    $addparam .= 'tag=' . $tagId . '&';
}

$searchstr = trim($_GET['search'] ?? '');
if ($searchstr !== '') {
    $safeSearch = "'" . $db->escapeString('%' . $searchstr . '%') . "'";
    $wherea[] = 'torrents.name LIKE ' . $safeSearch;
    $addparam .= 'search=' . urlencode($searchstr) . '&';
}

$column = 'id';
$ascdesc = 'DESC';
if (isset($_GET['sort'], $_GET['type']) && $_GET['sort'] !== '' && $_GET['type'] !== '') {
    switch ($_GET['sort']) {
        case '1': $column = 'name'; break;
        case '2': $column = 'numfiles'; break;
        case '3': $column = 'comments'; break;
        case '4': $column = 'added'; break;
        case '5': $column = 'size'; break;
        case '6': $column = 'times_completed'; break;
        case '7': $column = 'seeders'; break;
        case '8': $column = 'leechers'; break;
        case '9': $column = 'owner'; break;
        default: $column = 'id';
    }
    $ascdesc = ($_GET['type'] === 'asc') ? 'ASC' : 'DESC';
    $addparam .= 'sort=' . intval($_GET['sort']) . '&type=' . $_GET['type'] . '&';
}
$orderBy = 'ORDER BY torrents.pos_state DESC, torrents.' . $column . ' ' . $ascdesc;

$where = implode(' AND ', $wherea);

$listingOptions = [
    'where' => $where,
    'join_users' => ($column === 'owner'),
    'join_torrent_tags' => $tagId > 0,
    'tag_id' => $tagId,
];

$count = \App\Repositories\TorrentListingRepository::getCount($listingOptions);

$torrentsperpage = 24;
if (!empty($_GET['pageSize']) && ctype_digit($_GET['pageSize'])) {
    $torrentsperpage = min(100, (int) $_GET['pageSize']);
} elseif (!empty($CURUSER['torrentsperpage'])) {
    $torrentsperpage = (int) $CURUSER['torrentsperpage'];
} elseif (!empty($torrentsperpage_main)) {
    $torrentsperpage = (int) $torrentsperpage_main;
}
$torrentsperpage = min(100, $torrentsperpage);

if ($count) {
    list($pagertop, $pagerbottom, $limit, $offset, $size, $page) = pager($torrentsperpage, $count, 'torrents2.php?' . $addparam);
}

$assetVersion = max(
    filemtime(__DIR__ . '/styles/torrents2.css') ?: 0,
    filemtime(__DIR__ . '/js/torrents2.js') ?: 0
);
\Nexus\Nexus::css('styles/torrents2.css?v=' . $assetVersion, 'header', true);
\Nexus\Nexus::js('js/torrents2.js?v=' . $assetVersion, 'footer', true);

stdhead($lang_torrents['head_torrents'] ?? 'Torrents');

$cats = $genreList;
?>
<div class="t2-wrap">
    <div class="t2-header">
        <h1><?php echo $lang_torrents['head_torrents'] ?? 'Torrents' ?></h1>
        <div class="t2-switcher">
            <?php foreach (['table' => 'Table', 'card' => 'Cards', 'compact' => 'Compact'] as $v => $label): ?>
                <?php
                $query = $_GET;
                $query['view'] = $v;
                $url = 'torrents2.php?' . http_build_query($query);
                $active = $view === $v ? ' t2-switcher__btn--active' : '';
                ?>
                <a class="t2-switcher__btn<?php echo $active ?>" href="<?php echo $url ?>"><?php echo $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <form class="t2-filters" method="get" action="torrents2.php">
        <input type="hidden" name="view" value="<?php echo htmlspecialchars($view) ?>" />
        <label>
            <?php echo $lang_torrents['text_category'] ?? 'Category' ?>
            <select name="cat">
                <option value="0"><?php echo $lang_torrents['text_all'] ?? 'All' ?></option>
                <?php foreach ($cats as $cat): ?>
                    <option value="<?php echo (int) $cat['id'] ?>" <?php echo $catId == $cat['id'] ? 'selected="selected"' : '' ?>>
                        <?php echo htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <?php echo $lang_torrents['text_search'] ?? 'Search' ?>
            <input type="text" name="search" value="<?php echo htmlspecialchars($searchstr) ?>" placeholder="<?php echo $lang_torrents['text_name'] ?? 'Name' ?>" />
        </label>
        <button type="submit" class="t2-btn"><?php echo $lang_torrents['submit_search'] ?? 'Filter' ?></button>
    </form>

    <?php if ($count): ?>
        <?php print($pagertop ?? ''); ?>
        <?php
        $fieldsArr = \App\Models\Torrent::getFieldsForList(true);
        $rows = \App\Repositories\TorrentListingRepository::getList(array_merge($listingOptions, [
            'fields' => $fieldsArr,
            'search_box_id' => $sectiontype,
            'order_by' => $orderBy,
            'offset' => $offset ?? 0,
            'limit' => $size ?? $torrentsperpage,
        ]));
        $rows = apply_filter('torrent_list', $rows, $page ?? 1, $sectiontype, $searchstr);
        print(\App\Support\TorrentGrid::render($rows, $view, $sectiontype));
        print($pagerbottom ?? '');
        ?>
    <?php else: ?>
        <p class="t2-empty"><?php echo $lang_torrents['std_nothing_found'] ?? 'Nothing found.' ?></p>
    <?php endif; ?>
</div>
<?php
stdfoot();
