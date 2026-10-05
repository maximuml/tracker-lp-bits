<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\TorrentDownloadRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Enums\TorrentApprovalStatus;
use App\Enums\TorrentPosState;
use App\Models\SearchBox;
use App\Services\PermissionChecker;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Input;
use App\Support\Locale;
use App\Support\Log;
use App\Support\RedisGuard;
use App\Support\Strings;
use App\Support\TorrentBookmark;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class TorrentRssController extends LegacyController
{
    private TorrentRepositoryInterface $torrentRepository;

    private TorrentDownloadRepositoryInterface $downloadRepository;

    public function __construct(private readonly UserRepositoryInterface $userRepository, private readonly PermissionChecker $permissionChecker,
        TorrentRepositoryInterface $torrentRepository,
        TorrentDownloadRepositoryInterface $downloadRepository,
        private readonly CurrentUser $currentUser,
        private readonly LegacyRedisCache $legacyRedisCache,
    ) {
        $this->torrentRepository = $torrentRepository;
        $this->downloadRepository = $downloadRepository;
    }

    public function torrentrss(Request $request): Response
    {
        $cache = $this->legacyRedisCache;
        $currentUser = $this->currentUser->get() ?? [];
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

        $cacheKey = 'nexus_rss:'.$passkey.':'.hash('xxh128', http_build_query($filteredQuery));
        $cacheData = RedisGuard::attempt(static fn () => Cache::get($cacheKey));
        if ($cacheData && config('app.env') !== 'local') {
            Log::writeWithContext('rss get from cache');

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

        $baseQuery = $this->torrentRepository->newRssBaseQuery();

        $dllink = false;
        $inclbookmarked = 0;
        /** @var array<string, mixed> $rssUser */
        $rssUser = (array) RedisGuard::remember('user_passkey_'.$passkey.'_rss', 3600, function () use ($passkey) {
            return $this->userRepository->findByPasskey($passkey, ['id', 'enabled', 'parked', 'passkey'])?->toArray() ?? [];
        });

        if (empty($rssUser)) {
            return response('invalid passkey', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if (! $rssUser['enabled'] || $rssUser['parked']) {
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

        if (! SiteConfig::current()->torrent->approvalStatusNoneVisible() && ! $this->permissionChecker->userCan(PermissionEnum::STAFF_MEMBER->value, false, (int) ($rssUser['id'] ?? 0))) {
            $baseQuery->where('torrents.approval_status', TorrentApprovalStatus::ALLOW->value);
        }

        $browseMode = SiteConfig::current()->main->browseCat();
        $allBrowseCategoryId = SearchBox::listCategoryId($browseMode);
        $baseQuery->whereIn('torrents.category', $allBrowseCategoryId);

        $baseQuery->where('torrents.visible', 1);

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
                $posStates[] = TorrentPosState::STICKY_FIRST->value;
            }
            if (in_array('2', $stickyArr, true)) {
                $hasStickySecond = true;
                $posStates[] = TorrentPosState::STICKY_SECOND->value;
            }
            if (! empty($posStates)) {
                $prependIdArr = $this->torrentRepository->pluckIdsByPosStates($posStates)->toArray();
            }
        }

        if ($hasStickyFirst || $hasStickySecond) {
            $noNormalResults = true;
        }

        if (! $noNormalResults) {
            $normalQuery = clone $baseQuery;
            if ($hasStickyNormal) {
                $normalQuery->where('torrents.pos_state', TorrentPosState::NONE->value);
            }
            $normalSql = $normalQuery->toSql();
            $normalCacheKey = sprintf('nexus_rss:normal:%s', hash('xxh128', $normalSql.':'.$showrows));
            $normalRows = RedisGuard::remember($normalCacheKey, 300, function () use ($normalQuery, $showrows) {
                return $normalQuery->orderBy('torrents.id', 'desc')->limit($showrows)->get()->map(fn ($row) => (array) $row)->all();
            });
        }

        if (! empty($prependIdArr)) {
            $prependIds = array_map('intval', $prependIdArr);
            $prependIdStr = implode(',', $prependIds);
            $prependQuery = clone $baseQuery;
            $prependQuery->whereIn('torrents.id', $prependIds);
            $placeholders = implode(',', array_fill(0, count($prependIds), '?'));
            $prependCacheKey = sprintf('nexus_rss:prepend:%s', hash('xxh128', $prependQuery->toSql().':'.$prependIdStr));
            $prependRows = RedisGuard::remember($prependCacheKey, 300, function () use ($prependQuery, $placeholders, $prependIds) {
                return $prependQuery->orderByRaw("FIELD(torrents.id, {$placeholders})", $prependIds)->get()->map(fn ($row) => (array) $row)->all();
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
        $baseUrl = Url::absolute(SiteConfig::current()->basic->baseUrl() ?: Input::serverValue('HTTP_HOST', 'localhost'));
        $siteName = SiteConfig::current()->basic->siteName();
        $slogan = SiteConfig::current()->main->slogan();
        $siteEmail = SiteConfig::current()->main->siteEmail();
        $projectName = PROJECTNAME;
        $dateFounded = SiteConfig::current()->tweak->dateFounded();
        $year = substr($dateFounded, 0, 4);
        $yearFounded = $year !== '' ? $year : '2007';
        $copyright = 'Copyright (c) '.$siteName.' '.(date('Y') !== $yearFounded ? $yearFounded.'-' : '').date('Y').', all rights reserved';
        $httpHost = (string) (parse_url(Url::siteBase(), PHP_URL_HOST) ?: 'localhost');

        $hexEsc = function ($matches) {
            return sprintf('%02x', ord($matches[0]));
        };

        $items = [];
        foreach ($list as $row) {
            $ownerInfo = UserDisplay::row((int) ($row['owner'] ?? 0));
            $author = 'anonymous';
            if ($row['anonymous'] != 1) {
                if (! empty($ownerInfo)) {
                    $author = (string) ($ownerInfo['username'] ?? '');
                } else {
                    $author = Locale::trans('nexus.user_not_exists', [], null);
                }
            }

            $itemurl = $baseUrl.'/web/details/'.(int) ($row['id'] ?? 0);
            if ($dllink) {
                $itemdlurl = $this->downloadRepository->getDownloadUrl((int) ($row['id'] ?? 0), $rssUser);
            } else {
                $itemdlurl = $baseUrl.'/download?id='.(int) ($row['id'] ?? 0);
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

            $items[] = [
                'title' => new HtmlString($title),
                'url' => $itemurl,
                'content' => new HtmlString((string) Format::formatComment((string) ($row['descr'] ?? ''), true, false, false, false)),
                'author' => $author,
                'categoryId' => (int) ($row['category'] ?? 0),
                'categoryName' => (string) ($row['category_name'] ?? ''),
                'commentsUrl' => new HtmlString($baseUrl.'/web/details/'.(int) ($row['id'] ?? 0).'?cmtpage=0#startcomments'),
                'downloadUrl' => $itemdlurl,
                'size' => (int) ($row['size'] ?? 0),
                'guid' => preg_replace_callback('/./s', $hexEsc, Strings::padHash((string) ($row['info_hash'] ?? ''))),
                'pubDate' => date('r', strtotime((string) ($row['added'] ?? 'now')) ?: time()),
            ];
        }

        $xml = '<?xml version="1.0" encoding="utf-8"?>'
            .view('rss.torrents', [
                'channelTitle' => $siteName.' Torrents',
                'baseUrl' => new HtmlString($baseUrl),
                'description' => new HtmlString('Latest torrents from '.$siteName.' - '.htmlspecialchars($slogan)),
                'copyright' => $copyright,
                'siteEmail' => $siteEmail,
                'siteName' => $siteName,
                'pubDate' => date('r'),
                'projectName' => $projectName,
                'httpHost' => $httpHost,
                'items' => $items,
            ])->render();

        Log::writeWithContext('rss cache generated');
        RedisGuard::attempt(static fn () => Cache::put($cacheKey, $xml, 300));

        return response($xml, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }
}
