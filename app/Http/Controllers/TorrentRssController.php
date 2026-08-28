<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Repositories\TorrentRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Globals;
use App\Support\Http;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Permissions;
use App\Support\Strings;
use App\Support\TorrentBookmark;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TorrentRssController extends LegacyController
{
    private TorrentRepository $torrentRepository;

    public function __construct(TorrentRepository $torrentRepository)
    {
        $this->torrentRepository = $torrentRepository;
    }

    public function torrentrss(Request $request): Response
    {
        $cache = app(LegacyRedisCache::class);
        $currentUser = app(CurrentUser::class)->get() ?? [];
        $passkey = (string) ($request->input('passkey') ?? $currentUser['passkey'] ?? '');

        if ($passkey === '') {
            return response('require passkey', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $exactParams = ['inclbookmarked', 'paid', 'rows', 'icat', 'ismalldescr', 'isize', 'iuplder', 'search', 'search_mode', 'sticky', 'linktype'];
        $filteredQuery = [];
        foreach ($request->query->all() as $key => $value) {
            if (in_array($key, $exactParams, true)) {
                $filteredQuery[$key] = $value;

                continue;
            }
            if (preg_match('/^(cat|sou|med|cod|sta|pro|tea|aud)\d+$/', $key)) {
                $filteredQuery[$key] = $value;
            }
        }

        $cacheKey = 'nexus_rss:'.$passkey.':'.md5(http_build_query($filteredQuery));
        $cacheData = Cache::get($cacheKey);
        if ($cacheData && config('app.env') !== 'local') {
            Log::writeWithContext('rss get from cache', 'info');

            return response((string) $cacheData, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
        }

        $showrows = (int) $request->input('rows', 0);
        if ($showrows < 1 || $showrows > 50) {
            $showrows = 50;
        }

        $paidFilter = '0';
        if ($request->input('paid') !== null && in_array((string) $request->input('paid'), ['0', '1', '2'], true)) {
            $paidFilter = (string) $request->input('paid');
        }

        $baseQuery = DB::table('torrents')
            ->leftJoin('categories', 'torrents.category', '=', 'categories.id')
            ->leftJoin('torrent_extras', 'torrents.id', '=', 'torrent_extras.torrent_id')
            ->select('torrents.id', 'torrents.category', 'torrents.name', 'torrent_extras.descr', 'torrents.info_hash', 'torrents.size', 'torrents.added', 'torrents.anonymous', 'torrents.owner', 'categories.name as category_name');

        $dllink = false;
        $inclbookmarked = 0;
        $rssUser = (array) Cache::remember('user_passkey_'.$passkey.'_rss', 3600, function () use ($passkey) {
            $row = DB::table('users')->where('passkey', $passkey)->first(['id', 'enabled', 'parked', 'passkey']);

            return $row ? (array) $row : [];
        });

        if (empty($rssUser)) {
            return response('invalid passkey', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($rssUser['enabled'] === 'no' || $rssUser['parked'] === 'yes') {
            return response('account disabed or parked', 403, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($request->input('linktype') === 'dl') {
            $dllink = true;
        }

        $inclbookmarked = (int) $request->input('inclbookmarked', 0);
        if ($inclbookmarked === 1) {
            $bookmarkarray = TorrentBookmark::bookmarkArray($cache, (int) ($rssUser['id'] ?? 0));
            if (! empty($bookmarkarray)) {
                $baseQuery->whereIn('torrents.id', $bookmarkarray);
            }
        }

        if (! SiteConfig::current()->torrent->approvalStatusNoneVisible() && ! Permissions::userCan(PermissionEnum::STAFF_MEMBER->value, false, (int) ($rssUser['id'] ?? 0))) {
            $baseQuery->where('torrents.approval_status', Torrent::APPROVAL_STATUS_ALLOW);
        }

        $browseMode = SiteConfig::current()->main->browseCat();
        $allBrowseCategoryId = SearchBox::listCategoryId($browseMode);
        $baseQuery->whereIn('torrents.category', $allBrowseCategoryId);

        $baseQuery->where('torrents.visible', 'yes');

        if ($paidFilter === '0') {
            $baseQuery->where('torrents.price', 0);
        } elseif ($paidFilter === '1') {
            $baseQuery->where('torrents.price', '>', 0);
        }

        $applyRssFilter = function ($query, string $tablename = 'sources', string $itemname = 'source', string $getname = 'sou') use ($request) {
            $items = \App\Support\SearchBox::itemListWithContext($tablename, 0);
            $ids = [];
            foreach ($items as $item) {
                if ($request->input($getname.$item['id']) !== null) {
                    $ids[] = $item['id'];
                }
            }
            if (! empty($ids)) {
                $query->whereIn($itemname, $ids);
            }
        };

        $applyRssFilter($baseQuery, 'categories', 'category', 'cat');
        $applyRssFilter($baseQuery, 'sources', 'source', 'sou');
        $applyRssFilter($baseQuery, 'media', 'medium', 'med');
        $applyRssFilter($baseQuery, 'codecs', 'codec', 'cod');
        $applyRssFilter($baseQuery, 'standards', 'standard', 'sta');
        $applyRssFilter($baseQuery, 'processings', 'processing', 'pro');
        $applyRssFilter($baseQuery, 'audiocodecs', 'audiocodec', 'aud');

        $hasStickyFirst = false;
        $hasStickySecond = false;
        $hasStickyNormal = false;
        $noNormalResults = false;
        $prependIdArr = [];
        $prependRows = [];
        $normalRows = [];

        if ($request->input('sticky') !== null && $inclbookmarked === 0) {
            $stickyArr = explode(',', (string) $request->input('sticky'));
            $posStates = [];
            if (in_array('0', $stickyArr, true)) {
                $hasStickyNormal = true;
            }
            if (in_array('1', $stickyArr, true)) {
                $hasStickyFirst = true;
                $posStates[] = Torrent::POS_STATE_STICKY_FIRST;
            }
            if (in_array('2', $stickyArr, true)) {
                $hasStickySecond = true;
                $posStates[] = Torrent::POS_STATE_STICKY_SECOND;
            }
            if (! empty($posStates)) {
                $prependIdArr = Torrent::query()->whereIn('pos_state', $posStates)->pluck('id')->toArray();
            }
        }

        if ($hasStickyFirst || $hasStickySecond) {
            $noNormalResults = true;
        }

        if (! $noNormalResults) {
            $normalQuery = clone $baseQuery;
            if ($hasStickyNormal) {
                $normalQuery->where('torrents.pos_state', Torrent::POS_STATE_STICKY_NONE);
            }
            $normalSql = $normalQuery->toSql();
            $normalCacheKey = sprintf('nexus_rss:normal:%s', md5($normalSql.':'.$showrows));
            $normalRows = Cache::remember($normalCacheKey, 300, function () use ($normalQuery, $showrows) {
                return $normalQuery->orderBy('torrents.id', 'desc')->limit($showrows)->get()->map(fn ($row) => (array) $row)->all();
            });
        }

        if (! empty($prependIdArr)) {
            $prependIdStr = implode(',', array_map('intval', $prependIdArr));
            $prependQuery = clone $baseQuery;
            $prependQuery->whereIn('torrents.id', $prependIdArr);
            $prependCacheKey = sprintf('nexus_rss:prepend:%s', md5($prependQuery->toSql().':'.$prependIdStr));
            $prependRows = Cache::remember($prependCacheKey, 300, function () use ($prependQuery, $prependIdStr) {
                return $prependQuery->orderByRaw('FIELD(torrents.id, '.$prependIdStr.')')->get()->map(fn ($row) => (array) $row)->all();
            });
        }

        $list = [];
        foreach ($prependRows as $row) {
            $list[(int) $row['id']] = $row;
        }
        foreach ($normalRows as $row) {
            if (! isset($list[(int) $row['id']])) {
                $list[(int) $row['id']] = $row;
            }
        }

        $torrentRep = $this->torrentRepository;
        $baseUrl = Http::protocolPrefix(Url::isSecure()).(string) app(Globals::class)->get('BASEURL', '');
        $siteName = (string) app(Globals::class)->get('SITENAME', '');
        $slogan = (string) app(Globals::class)->get('SLOGAN', '');
        $siteEmail = (string) app(Globals::class)->get('SITEEMAIL', '');
        $projectName = (string) app(Globals::class)->get('PROJECTNAME', '');
        $dateFounded = (string) app(Globals::class)->get('datefounded', '');
        $year = substr($dateFounded, 0, 4);
        $yearFounded = $year !== '' ? $year : '2007';
        $copyright = 'Copyright (c) '.$siteName.' '.(date('Y') !== $yearFounded ? $yearFounded.'-' : '').date('Y').', all rights reserved';
        $httpHost = (string) $request->server->get('HTTP_HOST', 'localhost');

        $hexEsc = function ($matches) {
            return sprintf('%02x', ord($matches[0]));
        };

        $xml = '<?xml version="1.0" encoding="utf-8"?>';
        $xml .= '<rss version="2.0">';
        $xml .= '<channel>
        <title>'.addslashes($siteName.' Torrents').'</title>
        <link><![CDATA['.$baseUrl.']]></link>
        <description><![CDATA['.addslashes('Latest torrents from '.$siteName.' - '.htmlspecialchars($slogan)).']]></description>
        <language>zh-cn</language>
        <copyright>'.$copyright.'</copyright>
        <managingEditor>'.$siteEmail.' ('.$siteName.' Admin)</managingEditor>
        <webMaster>'.$siteEmail.' ('.$siteName.' Webmaster)</webMaster>
        <pubDate>'.date('r').'</pubDate>
        <generator>'.$projectName.' RSS Generator</generator>
        <docs><![CDATA[http://www.rssboard.org/rss-specification]]></docs>
        <ttl>60</ttl>
        <image>
            <url><![CDATA['.$baseUrl.'/pic/rss_logo.jpg]]></url>
            <title>'.addslashes($siteName.' Torrents').'</title>
            <link><![CDATA['.$baseUrl.']]></link>
            <width>100</width>
            <height>100</height>
            <description>'.addslashes($siteName.' Torrents').'</description>
        </image>';

        foreach ($list as $row) {
            $ownerInfo = UserDisplay::row((int) ($row['owner'] ?? 0));
            $author = 'anonymous';
            if ($row['anonymous'] !== 'yes') {
                if (! empty($ownerInfo)) {
                    $author = (string) ($ownerInfo['username'] ?? '');
                } else {
                    $author = Locale::trans('nexus.user_not_exists', [], null);
                }
            }

            $itemurl = $baseUrl.'/details.php?id='.(int) ($row['id'] ?? 0);
            if ($dllink) {
                $itemdlurl = $torrentRep->getDownloadUrl((int) ($row['id'] ?? 0), $rssUser);
            } else {
                $itemdlurl = $baseUrl.'/download.php?id='.(int) ($row['id'] ?? 0);
            }

            $title = '';
            if ($request->input('icat') !== null) {
                $title .= '['.($row['category_name'] ?? '').']';
            }
            $title .= $row['name'] ?? '';
            if ($request->input('isize') !== null) {
                $title .= '['.Format::size((int) ($row['size'] ?? 0)).']';
            }
            if ($request->input('iuplder') !== null) {
                $title .= '['.$author.']';
            }

            $content = Format::formatComment((string) ($row['descr'] ?? ''), true, false, false, false);

            $xml .= '<item>
            <title><![CDATA['.$title.']]></title>
            <link>'.$itemurl.'</link>
            <description><![CDATA['.$content.']]></description>
            <author>'.$author.'@'.$httpHost.' ('.$author.')</author>
            <category domain="'.$baseUrl.'/torrents.php?cat='.(int) ($row['category'] ?? 0).'">'.($row['category_name'] ?? '').'</category>
            <comments><![CDATA['.$baseUrl.'/details.php?id='.(int) ($row['id'] ?? 0).'&cmtpage=0#startcomments]]></comments>
            <enclosure url="'.$itemdlurl.'" length="'.(int) ($row['size'] ?? 0).'" type="application/x-bittorrent" />
            <guid isPermaLink="false">'.preg_replace_callback('/./s', $hexEsc, Strings::padHash((string) ($row['info_hash'] ?? ''))).'</guid>
            <pubDate>'.date('r', strtotime((string) ($row['added'] ?? 'now')) ?: time()).'</pubDate>
        </item>
';
        }

        $xml .= '</channel>
</rss>';

        Log::writeWithContext('rss cache generated', 'info');
        Cache::put($cacheKey, $xml, 300);

        return response($xml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }
}
