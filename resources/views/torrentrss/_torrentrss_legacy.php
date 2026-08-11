<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_HTTP_HOST = \App\Support\SupportContext::getServerValue('HTTP_HOST');
$__server_REQUEST_URI = \App\Support\SupportContext::getServerValue('REQUEST_URI');
$passkey = \App\Support\SupportContext::getQuery('passkey') ?? $CURUSER['passkey'] ?? '';
if (!$passkey) {
    echo "require passkey";
    return;
}
$exactParams = ['inclbookmarked', 'paid', 'rows', 'icat', 'ismalldescr', 'isize', 'iuplder', 'search', 'search_mode', 'sticky', 'linktype'];
$prefixedParams = ['cat', 'sou', 'med', 'cod', 'sta', 'pro', 'tea', 'aud'];
foreach (\App\Support\SupportContext::allQuery() as $key => $value) {
    if (in_array($key, $exactParams, true)) {
        continue;
    }
    if (preg_match('/^(cat|sou|med|cod|sta|pro|tea|aud)\d+$/', $key)) {
        continue;
    }
    \App\Support\SupportContext::removeQuery($key);
}
$cacheKey = "nexus_rss:$passkey:" . md5(http_build_query(\App\Support\SupportContext::allQuery()));
$cacheData = \Nexus\Database\NexusDB::cache_get($cacheKey);
if ($cacheData && nexus_env('APP_ENV') != 'local') {
    do_log("rss get from cache");
    header ("Content-type: text/xml");
    echo $cacheData;
    return;
}
function hex_esc($matches) {
	return sprintf("%02x", ord($matches[0]));
}
$dllink = false;

$showrows = intval(\App\Support\SupportContext::getQuery('rows') ?? 0);
if ($showrows < 1 || $showrows > 50) {
    $showrows = 50;
}

$paidFilter = '0';
if (((\App\Support\SupportContext::getQuery('paid') !== null)) && in_array(\App\Support\SupportContext::getQuery('paid'), ['0', '1', '2'], true)) {
    $paidFilter = \App\Support\SupportContext::getQuery('paid');
}

$baseQuery = \Nexus\Database\NexusDB::table('torrents')
    ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
    ->leftJoin('torrent_extras', 'torrents.id', '=', 'torrent_extras.torrent_id')
    ->select('torrents.id', 'torrents.category', 'torrents.name', 'torrent_extras.descr', 'torrents.info_hash', 'torrents.size', 'torrents.added', 'torrents.anonymous', 'torrents.owner', 'categories.name as category_name');

if ($passkey) {
    $user = \Nexus\Database\NexusDB::remember('user_passkey_'.$passkey.'_rss', 3600, function () use ($passkey) {
        $row = \Nexus\Database\NexusDB::table('users')->where('passkey', $passkey)->first(['id', 'enabled', 'parked', 'passkey']);
        return $row ? (array) $row : [];
    });
	if (!$user) {
		echo "invalid passkey";
		return;
	} elseif ($user['enabled'] == 'no' || $user['parked'] == 'yes') {
		echo "account disabed or parked";
		return;
	} elseif (((\App\Support\SupportContext::getQuery('linktype') !== null)) && \App\Support\SupportContext::getQuery('linktype') == 'dl') {
		$dllink = true;
	}

    $inclbookmarked = intval(\App\Support\SupportContext::getQuery('inclbookmarked') ?? 0);
    if ($inclbookmarked == 1) {
        $bookmarkarray = return_torrent_bookmark_array($user['id']);
        if (!empty($bookmarkarray)) {
            $baseQuery->whereIn('torrents.id', $bookmarkarray);
        }
    }

    if (!\App\Support\Config\SiteConfig::current()->torrent->approvalStatusNoneVisible() && !\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::STAFF_MEMBER, \App\Models\User::find($user['id']))) {
        $baseQuery->where('torrents.approval_status', \App\Models\Torrent::APPROVAL_STATUS_ALLOW);
    }

    $browseMode = \App\Support\Config\SiteConfig::current()->main->browseCat();
    $allBrowseCategoryId = \App\Models\SearchBox::listCategoryId($browseMode);
    $baseQuery->whereIn('torrents.category', $allBrowseCategoryId);
}

$baseQuery->where('torrents.visible', 'yes');

if ($paidFilter === '0') {
    $baseQuery->where('torrents.price', 0);
} elseif ($paidFilter === '1') {
    $baseQuery->where('torrents.price', '>', 0);
}

function applyRssFilter($query, $tablename = "sources", $itemname = "source", $getname = "sou")
{
    $items = searchbox_item_list($tablename, 0);
    $ids = [];
    foreach ($items as $item) {
        if (!empty(\App\Support\SupportContext::getQuery($getname.$item['id']))) {
            $ids[] = $item['id'];
        }
    }
    if (!empty($ids)) {
        $query->whereIn($itemname, $ids);
    }
}

applyRssFilter($baseQuery, "categories", "category", "cat");
applyRssFilter($baseQuery, "sources", "source", "sou");
applyRssFilter($baseQuery, "media", "medium", "med");
applyRssFilter($baseQuery, "codecs", "codec", "cod");
applyRssFilter($baseQuery, "standards", "standard", "sta");
applyRssFilter($baseQuery, "processings", "processing", "pro");
applyRssFilter($baseQuery, "audiocodecs", "audiocodec", "aud");

$hasStickyFirst = $hasStickySecond = $hasStickyNormal = $noNormalResults = false;
$prependIdArr = $prependRows = $normalRows = [];
$stickyWhere = $normalWhere = '';
if (((\App\Support\SupportContext::getQuery('sticky') !== null)) && $inclbookmarked == 0) {
    $stickyArr = explode(',', \App\Support\SupportContext::getQuery('sticky'));
    //Only handle sticky first + second
    $posStates = [];
    if (in_array('0', $stickyArr, true)) {
        $hasStickyNormal = true;
    }
    if (in_array('1', $stickyArr, true)) {
        $hasStickyFirst = true;
        $posStates[] = \App\Models\Torrent::POS_STATE_STICKY_FIRST;
    }
    if (in_array('2', $stickyArr, true)) {
        $hasStickySecond = true;
        $posStates[] = \App\Models\Torrent::POS_STATE_STICKY_SECOND;
    }
    if (!empty($posStates)) {
        $prependIdArr = \App\Models\Torrent::query()->whereIn('pos_state', $posStates)->pluck('id')->toArray();
    }
}
$prependIdArr = apply_filter("sticky_promotion_torrent_ids", $prependIdArr);
if ($hasStickyNormal) {
    $stickyWhere = sprintf("torrents.pos_state = '%s'", \App\Models\Torrent::POS_STATE_STICKY_NONE);
} elseif ($hasStickyFirst || $hasStickySecond) {
    $noNormalResults = true;
}

if (!$noNormalResults) {
    $normalQuery = clone $baseQuery;
    if ($hasStickyNormal) {
        $normalQuery->where('torrents.pos_state', \App\Models\Torrent::POS_STATE_STICKY_NONE);
    }
    $normalSql = $normalQuery->toSql();
    $normalCacheKey = sprintf("nexus_rss:normal:%s", md5($normalSql . ':' . $showrows));
    $normalRows = \Nexus\Database\NexusDB::remember($normalCacheKey, 300, function () use ($normalQuery, $showrows) {
        return $normalQuery->orderBy('torrents.id', 'desc')->limit($showrows)->get()->map(fn ($row) => (array) $row)->all();
    });
}
if (!empty($prependIdArr)) {
    $prependIdStr = implode(',', array_map('intval', $prependIdArr));
    $prependQuery = clone $baseQuery;
    $prependQuery->whereIn('torrents.id', $prependIdArr);
    $prependCacheKey = sprintf("nexus_rss:prepend:%s", md5($prependQuery->toSql() . ':' . $prependIdStr));
    $prependRows = \Nexus\Database\NexusDB::remember($prependCacheKey, 300, function () use ($prependQuery, $prependIdStr) {
        return $prependQuery->orderByRaw('FIELD(torrents.id, ' . $prependIdStr . ')')->get()->map(fn ($row) => (array) $row)->all();
    });
}
$list = [];
foreach ($prependRows as $row) {
    $list[$row['id']] = $row;
}
foreach ($normalRows as $row) {
    if (!(isset($list[$row['id']]))) {
        $list[$row['id']] = $row;
    }
}

//dd($prependIdArr, $prependRows, $normalRows, $list, $startindex,last_query());

$torrentRep = new \App\Repositories\TorrentRepository();
$url = get_protocol_prefix().$BASEURL;
$year = substr($datefounded, 0, 4);
$yearfounded = ($year ? $year : 2007);
$copyright = "Copyright (c) ".$SITENAME." ".(date("Y") != $yearfounded ? $yearfounded."-" : "").date("Y").", all rights reserved";
$xml = "<?xml version=\"1.0\" encoding=\"utf-8\"?>";
//The commented version passed feed validator at http://www.feedvalidator.org
/*print('
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:atom="http://www.w3.org/2005/Atom">');*/
$xml .= '<rss version="2.0">';
$xml .= '<channel>
		<title>' . addslashes($SITENAME.' Torrents'). '</title>
		<link><![CDATA[' . $url . ']]></link>
		<description><![CDATA[' . addslashes('Latest torrents from '.$SITENAME.' - '.htmlspecialchars($SLOGAN)) . ']]></description>
		<language>zh-cn</language>
		<copyright>'.$copyright.'</copyright>
		<managingEditor>'.$SITEEMAIL.' ('.$SITENAME.' Admin)</managingEditor>
		<webMaster>'.$SITEEMAIL.' ('.$SITENAME.' Webmaster)</webMaster>
		<pubDate>'.date('r').'</pubDate>
		<generator>'.PROJECTNAME.' RSS Generator</generator>
		<docs><![CDATA[http://www.rssboard.org/rss-specification]]></docs>
		<ttl>60</ttl>
		<image>
			<url><![CDATA[' . $url.'/pic/rss_logo.jpg'. ']]></url>
			<title>' . addslashes($SITENAME.' Torrents') . '</title>
			<link><![CDATA[' . $url . ']]></link>
			<width>100</width>
			<height>100</height>
			<description>' . addslashes($SITENAME.' Torrents') . '</description>
		</image>';
/*print('
		<atom:link href="'.$url.$__server_REQUEST_URI.'" rel="self" type="application/rss+xml" />');*/
//print('
//');
foreach ($list as $row)
{
    $ownerInfo = get_user_row($row['owner']);
	$title = "";
	if ($row['anonymous'] == 'yes') {
        $author = 'anonymous';
    } elseif (!empty($ownerInfo)) {
        $author = $ownerInfo['username'];
    } else {
        $author = nexus_trans("nexus.user_not_exists");
    }
	$itemurl = $url."/details.php?id=".$row['id'];
	if ($dllink)
		$itemdlurl = $torrentRep->getDownloadUrl($row['id'], $user);
	else $itemdlurl = $url."/download.php?id=".$row['id'];
	if (!empty(\App\Support\SupportContext::getQuery('icat'))) $title .= "[".$row['category_name']."]";
	$title .= $row['name'];
	if (!empty(\App\Support\SupportContext::getQuery('isize'))) $title .= "[".\App\Support\Format::size($row['size'])."]";
	if (!empty(\App\Support\SupportContext::getQuery('iuplder'))) $title .= "[".$author."]";
	$content = format_comment($row['descr'], true, false, false, false);
	$xml .= '<item>
			<title><![CDATA['.$title.']]></title>
			<link>'.$itemurl.'</link>
			<description><![CDATA['.$content.']]></description>
';
//print('			<dc:creator>'.$author.'</dc:creator>');
$xml .= '<author>'.$author.'@'.$__server_HTTP_HOST.' ('.$author.')</author>';
$xml .= '<category domain="'.$url.'/torrents.php?cat='.$row['category'].'">'.$row['category_name'].'</category>
			<comments><![CDATA['.$url.'/details.php?id='.$row['id'].'&cmtpage=0#startcomments]]></comments>
			<enclosure url="'.$itemdlurl.'" length="'.$row['size'].'" type="application/x-bittorrent" />
			<guid isPermaLink="false">'.preg_replace_callback('/./s', 'hex_esc', hash_pad($row['info_hash'])).'</guid>
			<pubDate>'.date('r',strtotime($row['added'])).'</pubDate>
		</item>
';
}
$xml .= '</channel>
</rss>';
do_log("rss cache generated");
\Nexus\Database\NexusDB::cache_put($cacheKey, $xml, 300);
header ("Content-type: text/xml");
echo $xml;
