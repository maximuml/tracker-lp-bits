<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Enums\BookmarkFilter;
use App\Enums\PeerSeeder;
use App\Enums\SnatchFinished;
use App\Enums\TorrentVisible;
use App\Exceptions\NexusException;
use App\Http\Resources\TorrentResource;
use App\Models\AudioCodec;
use App\Models\Bookmark;
use App\Models\Category;
use App\Models\Codec;
use App\Models\Media;
use App\Models\Peer;
use App\Models\Processing;
use App\Models\SearchBox;
use App\Models\Snatch;
use App\Models\Source;
use App\Models\Standard;
use App\Models\Torrent;
use App\Models\TorrentTag;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Description;
use App\Support\Format;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Strings;
use App\Utils\ApiQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Torrent repository: listing, detail, peer/snatch, and presentation helpers.
 *
 * Purchase, download, and moderation logic has been extracted to:
 *
 * @see TorrentPurchaseRepository
 * @see TorrentDownloadRepository
 * @see TorrentModerationRepository
 */
class TorrentRepository extends BaseRepository
{
    /** @var array<int, string> */
    private static array $defaultLoadRelationships = [
        'basic_category', 'basic_category.search_box',
        'basic_audiocodec', 'basic_codec', 'basic_medium',
        'basic_source', 'basic_processing', 'basic_standard', ];

    /** @var array<int, string> */
    private static array $allowIncludes = ['user', 'extra', 'tags'];

    /** @var array<int, string> */
    private static array $allowIncludeCounts = ['thank_users', 'reward_logs'];

    /** @var array<int, string> */
    private static array $allowIncludeFields = [
        'has_bookmarked', 'has_thanked', 'has_rewarded',
        'description', 'download_url', 'active_status',
    ];

    /** @var array<int, string> */
    private static array $allowFilters = [
        'title', 'category', 'source', 'medium', 'codec', 'audiocodec', 'standard', 'processing',
        'owner', 'visible', 'added', 'size', 'sp_state', 'leechers', 'seeders', 'times_completed',
        'bookmark',
    ];

    /** @var array<int, string> */
    private static array $allowSorts = ['id', 'comments', 'size', 'seeders', 'leechers', 'times_completed'];

    /**
     * fetch torrent list
     *
     * @return mixed
     */
    public function getList(Request $request, User $user, ?string $sectionName = null)
    {
        if (empty($sectionName)) {
            $sectionId = (int) SearchBox::getBrowseMode();
            $searchBox = SearchBox::query()->find($sectionId);
        } else {
            $searchBox = SearchBox::query()->where('name', $sectionName)->first();
        }
        if (! $searchBox instanceof SearchBox) {
            throw new NexusException(Locale::trans('upload.invalid_section', [], null));
        }
        $categoryIdList = $searchBox->categories()->pluck('id')->toArray();
        // query this info default
        $query = Torrent::query()->with(self::$defaultLoadRelationships)
            ->whereIn('category', $categoryIdList)
            ->orderBy('pos_state', 'desc');
        $apiQueryBuilder = ApiQueryBuilder::for(TorrentResource::NAME, $query, $request)
            ->allowIncludes(self::$allowIncludes)
            ->allowIncludeCounts(self::$allowIncludeCounts)
            ->allowIncludeFields(self::$allowIncludeFields)
            ->allowFilters(self::$allowFilters)
            ->allowSorts(self::$allowSorts)
            ->registerCustomFilter('title', function (Builder $query, Request $request) {
                $title = $request->input(ApiQueryBuilder::PARAM_NAME_FILTER.'.title');
                $title = trim(str_replace('.', '', (string) $title));
                if ($title) {
                    $titleParts = explode(' ', $title);
                    $keywordCount = 1;
                    foreach ($titleParts as $titlePart) {
                        if ($keywordCount > 3) {
                            break;
                        }
                        $titlePart = trim($titlePart);
                        $query->where(function (Builder $query) use ($titlePart) {
                            $query->where('name', 'like', '%'.$titlePart.'%');
                        });
                        $keywordCount++;
                    }
                }
            })
            ->registerCustomFilter('bookmark', function (Builder $query, Request $request) use ($user) {
                $filterBookmark = $request->input(ApiQueryBuilder::PARAM_NAME_FILTER.'.bookmark');
                if ($filterBookmark === BookmarkFilter::INCLUDE->value) {
                    $query->whereHas('bookmarks', function (Builder $query) use ($user) {
                        $query->where('userid', $user->id);
                    });
                } elseif ($filterBookmark === BookmarkFilter::EXCLUDE->value) {
                    $query->whereDoesntHave('bookmarks', function (Builder $query) use ($user) {
                        $query->where('userid', $user->id);
                    });
                }
            })
            ->registerCustomFilter('visible', function (Builder $query, Request $request) {
                $filterVisible = $request->input(ApiQueryBuilder::PARAM_NAME_FILTER.'.visible', Torrent::FILTER_VISIBLE_YES);
                if ($filterVisible === Torrent::FILTER_VISIBLE_YES) {
                    $query->where('visible', TorrentVisible::YES->value);
                } elseif ($filterVisible === Torrent::FILTER_VISIBLE_NO) {
                    $query->where('visible', TorrentVisible::NO->value);
                }
            });
        $query = $apiQueryBuilder->build();
        if (! $apiQueryBuilder->hasSort() || ! $apiQueryBuilder->hasSort('id')) {
            $query->orderBy('id', 'desc');
        }
        Logger::writeWithContext((string) 'before query torrent list', (string) 'info', (bool) false);
        $torrents = $query->paginate($this->getPerPageFromRequest($request));
        Logger::writeWithContext((string) 'after query torrent list', (string) 'info', (bool) false);

        return $this->appendIncludeFields($apiQueryBuilder, $user, $torrents);
    }

    /**
     * @return mixed
     */
    public function getDetail(int $id, User $user)
    {
        // query this info default
        $query = Torrent::query()->with(self::$defaultLoadRelationships);
        $apiQueryBuilder = ApiQueryBuilder::for(TorrentResource::NAME, $query)
            ->allowIncludes(self::$allowIncludes)
            ->allowIncludeCounts(self::$allowIncludeCounts)
            ->allowIncludeFields(self::$allowIncludeFields);
        Logger::writeWithContext((string) 'before query torrent detail', (string) 'info', (bool) false);
        $torrent = $apiQueryBuilder->build()->findOrFail($id);
        Logger::writeWithContext((string) 'before query torrent detail', (string) 'info', (bool) false);
        $torrentList = $this->appendIncludeFields($apiQueryBuilder, $user, [$torrent]);

        return $torrentList[0];
    }

    /**
     * @param  mixed  $torrentList
     * @return mixed
     */
    private function appendIncludeFields(ApiQueryBuilder $apiQueryBuilder, User $user, $torrentList)
    {
        $torrentIdArr = $bookmarkData = $thankData = $rewardData = $activeData = [];
        foreach ($torrentList as $torrent) {
            $torrentIdArr[] = $torrent->id;
        }
        unset($torrent);
        if ($hasFieldHasBookmarked = $apiQueryBuilder->hasIncludeField('has_bookmarked')) {
            $bookmarkData = $user->bookmarks()->whereIn('torrentid', $torrentIdArr)->get()->keyBy('torrentid');
        }
        if ($hasFieldHasThanked = $apiQueryBuilder->hasIncludeField('has_thanked')) {
            $thankData = $user->thank_torrent_logs()->whereIn('torrentid', $torrentIdArr)->get()->keyBy('torrentid');
        }
        if ($hasFieldHasRewarded = $apiQueryBuilder->hasIncludeField('has_rewarded')) {
            $rewardData = $user->reward_torrent_logs()->whereIn('torrentid', $torrentIdArr)->get()->keyBy('torrentid');
        }
        if ($hasFieldActiveStatus = $apiQueryBuilder->hasIncludeField('active_status')) {
            $torrentModule = new \Nexus\Torrent\Torrent;
            $activeData = $torrentModule->listLeechingSeedingStatus($user->id, $torrentIdArr);
        }
        Logger::writeWithContext((string) 'after prepare has data', (string) 'info', (bool) false);

        $downloadRepo = new TorrentDownloadRepository;
        foreach ($torrentList as $torrent) {
            $id = $torrent->id;
            if ($hasFieldHasBookmarked) {
                $torrent->has_bookmarked = $bookmarkData->has($id);
            }
            if ($hasFieldHasThanked) {
                $torrent->has_thanked = $thankData->has($id);
            }
            if ($hasFieldHasRewarded) {
                $torrent->has_rewarded = $rewardData->has($id);
            }
            if ($hasFieldActiveStatus) {
                $torrent->active_status = $activeData[$id] ?? null;
            }

            if ($apiQueryBuilder->hasIncludeField('description') && $apiQueryBuilder->hasInclude('extra')) {
                $descriptionArr = Description::parse($torrent->extra->descr ?? '');
                $torrent->description = $descriptionArr;
                $torrent->images = Description::imageFromDescription($descriptionArr);
            }
            if ($apiQueryBuilder->hasIncludeField('download_url')) {
                $torrent->download_url = $downloadRepo->getDownloadUrl($id, $user);
            }
        }
        Logger::writeWithContext((string) 'after fill has data', (string) 'info', (bool) false);

        return $torrentList;
    }

    /**
     * @return mixed
     */
    public function getSearchBox(?int $id = null)
    {
        if ($id === null) {
            $id = SiteConfig::current()->main->browseCat();
        }
        $searchBox = SearchBox::query()->findOrFail((int) $id);
        $category = $searchBox->categories()->orderBy('sort_index')->orderBy('id')->get();
        $modalRows = [];
        $modalRows[] = $categoryFormatted = $this->formatRow(Category::getLabelName(), $category, 'category');
        if ($searchBox->showsubcat) {
            if ($searchBox->showsource) {
                $source = Source::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Source::getLabelName(), $source, 'source');
            }
            if ($searchBox->showmedium) {
                $media = Media::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Media::getLabelName(), $media, 'medium');
            }
            if ($searchBox->showcodec) {
                $codec = Codec::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Codec::getLabelName(), $codec, 'codec');
            }
            if ($searchBox->showstandard) {
                $standard = Standard::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Standard::getLabelName(), $standard, 'standard');
            }
            if ($searchBox->showprocessing) {
                $processing = Processing::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Processing::getLabelName(), $processing, 'processing');
            }
            if ($searchBox->showaudiocodec) {
                $audioCodec = AudioCodec::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(AudioCodec::getLabelName(), $audioCodec, 'audio_codec');
            }
        }
        $results = [];
        $categories = $categoryFormatted['rows'];
        $categories[0]['active'] = 1;
        $results['categories'] = $categories;
        $results['modal_rows'] = $modalRows;

        return $results;
    }

    /**
     * @param  mixed  $header
     * @param  mixed  $items
     * @param  mixed  $name
     * @return mixed
     */
    private function formatRow($header, $items, $name)
    {
        $result['header'] = $header;
        $result['rows'][] = [
            'label' => 'All',
            'value' => 0,
            'name' => $name,
            'active' => 1,
        ];
        foreach ($items as $value) {
            $item = [
                'label' => $value->name,
                'value' => $value->id,
                'name' => $name,
                'active' => 0,
            ];
            $result['rows'][] = $item;
        }

        return $result;
    }

    /**
     * @param  mixed  $torrentId
     * @return array<int|string, mixed>
     */
    public function listPeers($torrentId)
    {
        $seederList = $leecherList = collect();
        $peers = Peer::query()
            ->where('torrent', $torrentId)
            ->groupBy('peer_id')
            ->with(['user', 'relative_torrent'])
            ->get()
            ->groupBy('seeder');
        $seederGroup = $peers->get(PeerSeeder::YES->value);
        if ($seederGroup instanceof Collection) {
            $seederList = $seederGroup->sort(function ($a, $b) {
                $x = $a->uploaded;
                $y = $b->uploaded;
                if ($x == $y) {
                    return 0;
                }
                if ($x < $y) {
                    return 1;
                }

                return -1;
            });
            $seederList = $this->formatPeers($seederList);
        }
        $leecherGroup = $peers->get(PeerSeeder::NO->value);
        if ($leecherGroup instanceof Collection) {
            $leecherList = $leecherGroup->sort(function ($a, $b) {
                $x = $a->to_go;
                $y = $b->to_go;
                if ($x == $y) {
                    return 0;
                }
                if ($x < $y) {
                    return -1;
                }

                return 1;
            });
            $leecherList = $this->formatPeers($leecherList);
        }

        return [
            'seeder_list' => $seederList,
            'leecher_list' => $leecherList,
        ];

    }

    /** @param  mixed  $peer */
    public function getPeerUploadSpeed($peer): string
    {
        $diff = $peer->uploaded - $peer->uploadoffset;
        $seconds = max(1, $peer->started->diffInSeconds($peer->last_action, true));

        return Format::size($diff / $seconds).'/s';
    }

    /** @param  mixed  $peer */
    public function getPeerDownloadSpeed($peer): string
    {
        $diff = $peer->downloaded - $peer->downloadoffset;
        if ($peer->isSeeder()) {
            $seconds = max(1, $peer->started->diffInSeconds($peer->finishedat, true));
        } else {
            $seconds = max(1, $peer->started->diffInSeconds($peer->last_action, true));
        }

        return Format::size($diff / $seconds).'/s';
    }

    /** @param  mixed  $peer */
    public function getDownloadProgress($peer): string
    {
        return sprintf('%.2f%%', 100 * (1 - ($peer->to_go / $peer->relative_torrent->size)));
    }

    /**
     * @param  mixed  $peer
     * @return mixed
     */
    public function getShareRatio($peer)
    {
        if ($peer->downloaded) {
            $ratio = floor(($peer->uploaded / $peer->downloaded) * 1000) / 1000;
        } elseif ($peer->uploaded) {
            // @todo 读语言文件
            $ratio = '无限';
        } else {
            $ratio = '---';
        }

        return $ratio;
    }

    /**
     * @param  mixed  $peers
     * @return mixed
     */
    private function formatPeers($peers)
    {
        foreach ($peers as &$item) {
            $item->upload_text = sprintf('%s@%s', Format::size($item->uploaded), $this->getPeerUploadSpeed($item));
            $item->download_text = sprintf('%s@%s', Format::size($item->downloaded), $this->getPeerDownloadSpeed($item));
            $item->download_progress = $this->getDownloadProgress($item);
            $item->share_ratio = $this->getShareRatio($item);
            $item->connect_time_total = $item->started->diffForHumans();
            $item->last_action_human = $item->last_action->diffForHumans();
            $item->agent_human = htmlspecialchars(Strings::userAgentClient($item->agent));
        }

        return $peers;
    }

    /**
     * @param  mixed  $torrentId
     * @return mixed
     */
    public function listSnatches($torrentId)
    {
        $snatches = Snatch::query()
            ->where('torrentid', $torrentId)
            ->where('finished', SnatchFinished::YES->value)
            ->with(['user'])
            ->orderBy('completedat', 'desc')
            ->paginate();

        return $snatches;
    }

    /**
     * @param  mixed  $snatch
     * @return mixed
     */
    public function getSnatchUploadSpeed($snatch)
    {
        if ($snatch->seedtime <= 0) {
            $speed = Format::size(0);
        } else {
            $speed = Format::size($snatch->uploaded / ($snatch->seedtime + $snatch->leechtime));
        }

        return "$speed/s";
    }

    /**
     * @param  mixed  $snatch
     * @return mixed
     */
    public function getSnatchDownloadSpeed($snatch)
    {
        if ($snatch->leechtime <= 0) {
            $speed = Format::size(0);
        } else {
            $speed = Format::size($snatch->downloaded / $snatch->leechtime);
        }

        return "$speed/s";
    }

    /**
     * @param  array<int|string, mixed>  $torrentInfo
     * @param  mixed  $size
     * @param  mixed  $verticalAlign
     * @return mixed
     */
    public function getPaidIcon(array $torrentInfo, $size = 16, $verticalAlign = 'sub')
    {
        if (! isset($torrentInfo['price']) || $torrentInfo['price'] <= 0) {
            return '';
        }

        return sprintf('<span title="%s" style="vertical-align: %s"><svg t="1676058062789" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="3406" width="%s" height="%s"><path d="M554.666667 810.666667v42.666666h-85.333334v-42.666666c-93.866667 0-170.666667-76.8-170.666666-170.666667h85.333333c0 46.933333 38.4 85.333333 85.333333 85.333333v-170.666666c-93.866667 0-170.666667-76.8-170.666666-170.666667s76.8-170.666667 170.666666-170.666667V170.666667h85.333334v42.666666c93.866667 0 170.666667 76.8 170.666666 170.666667h-85.333333c0-46.933333-38.4-85.333333-85.333333-85.333333v170.666666h17.066666c29.866667 0 68.266667 17.066667 98.133334 42.666667 34.133333 29.866667 59.733333 76.8 59.733333 128-4.266667 93.866667-81.066667 170.666667-174.933333 170.666667z m0-85.333334c46.933333 0 85.333333-38.4 85.333333-85.333333s-38.4-85.333333-85.333333-85.333333v170.666666zM469.333333 298.666667c-46.933333 0-85.333333 38.4-85.333333 85.333333s38.4 85.333333 85.333333 85.333333V298.666667z" fill="#CD7F32" p-id="3407"></path></svg></span>', Locale::trans('torrent.paid_torrent', [], null), $verticalAlign, $size, $size);
    }

    /**
     * @param  mixed  $name
     * @param  mixed  $value
     * @param  mixed  $noteText
     * @param  mixed  $btnText
     * @param  mixed  $btnId
     * @param  mixed  $btnOnClick
     */
    public function buildUploadFieldInput($name, $value, $noteText, $btnText, $btnId = '', $btnOnClick = ''): string
    {
        $btn = $note = '';
        if ($btnText) {
            $idAttr = $btnId ? ' id="'.htmlspecialchars($btnId, ENT_QUOTES, 'UTF-8').'"' : '';
            $onClickAttr = $btnOnClick ? ' onclick="'.htmlspecialchars($btnOnClick, ENT_QUOTES, 'UTF-8').'"' : '';
            $btn = '<div><input type="button" class="nexus-action-btn" value="'.$btnText.'"'.$idAttr.$onClickAttr.'></div>';
        }
        if ($noteText) {
            $note = '<span class="medium">'.$noteText.'</span>';
        }
        $input = <<<HTML
<div class="nexus-input-box" style="display: flex">
    <div style="display: flex;flex-direction: column;flex-grow: 1">
        <input type="text" id="$name" name="$name" value="{$value}">
        $note
    </div>
    $btn
</div>
HTML;

        return $input;
    }

    /**
     * Get the latest comment for a torrent, or null if none exists.
     *
     * @return array<string, mixed>|null
     */
    public function getLastComment(int $torrentId): ?array
    {
        $lastcom = DB::table('comments')->where('torrent', $torrentId)->orderBy('id', 'desc')->first();

        return $lastcom ? array_merge((array) $lastcom, array_values((array) $lastcom)) : null;
    }

    /**
     * Get torrent tag records keyed by torrent id.
     *
     * @param  array<int, int>  $torrentIds
     * @return Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, TorrentTag>>
     */
    public function getTorrentTagsGrouped(array $torrentIds)
    {
        return TorrentTag::query()->whereIn('torrent_id', $torrentIds)->get()->groupBy('torrent_id');
    }

    /**
     * Fetch a torrent as an array for the legacy "torrent to user" value calculation.
     *
     * @return array<string, mixed>|null
     */
    public function findForUserValue(int $torrentId): ?array
    {
        return Torrent::query()->find($torrentId)?->toArray();
    }

    /**
     * Return the bookmarked torrent ids for a user.
     *
     * Mirrors the legacy {@see TorrentBookmark::bookmarkArray()}.
     *
     * @return array<int, int>
     */
    public function getBookmarkTorrentIds(int $userId): array
    {
        $rows = Bookmark::query()->where('userid', $userId)->pluck('torrentid')->all();

        if (empty($rows)) {
            return [0];
        }

        return array_map(fn ($id) => (int) $id, $rows);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Delegating methods — backward compatibility for callers not yet updated
    //  to use TorrentPurchaseRepository, TorrentDownloadRepository, or
    //  TorrentModerationRepository directly.
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param  mixed  $torrentId
     */
    public function loadBoughtUser($torrentId): int
    {
        return (new TorrentPurchaseRepository)->loadBoughtUser($torrentId);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @param  mixed  $buyLogId
     */
    public function addBuySuccessCache($uid, $torrentId, $buyLogId): void
    {
        (new TorrentPurchaseRepository)->addBuySuccessCache($uid, $torrentId, $buyLogId);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     */
    public function hasBuySuccessCache($uid, $torrentId): bool
    {
        return (new TorrentPurchaseRepository)->hasBuySuccessCache($uid, $torrentId);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     */
    public function hasBuySuccess($uid, $torrentId): bool
    {
        return (new TorrentPurchaseRepository)->hasBuySuccess($uid, $torrentId);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     */
    public function getBuyStatus($uid, $torrentId): int
    {
        return (new TorrentPurchaseRepository)->getBuyStatus($uid, $torrentId);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     */
    public function addBuyFailCache($uid, $torrentId): void
    {
        (new TorrentPurchaseRepository)->addBuyFailCache($uid, $torrentId);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     */
    public function getBuyFailCache($uid, $torrentId): int
    {
        return (new TorrentPurchaseRepository)->getBuyFailCache($uid, $torrentId);
    }

    /**
     * @param  mixed  $id
     * @param  array<int|string, mixed>|User  $user
     */
    public function getDownloadUrl($id, array|User $user): string
    {
        return (new TorrentDownloadRepository)->getDownloadUrl($id, $user);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $user
     */
    public function encryptDownHash($id, $user): string
    {
        return (new TorrentDownloadRepository)->encryptDownHash($id, $user);
    }

    /**
     * @param  mixed  $downHash
     * @param  mixed  $user
     * @return array<int|string, mixed>
     */
    public function decryptDownHash($downHash, $user)
    {
        return (new TorrentDownloadRepository)->decryptDownHash($downHash, $user);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $uid
     * @param  mixed  $initializeIfNotExists
     */
    public function getTrackerReportAuthKey($id, $uid, $initializeIfNotExists = false): string
    {
        return (new TorrentDownloadRepository)->getTrackerReportAuthKey($id, $uid, $initializeIfNotExists);
    }

    /**
     * @param  mixed  $authKey
     * @return array<int|string, mixed>
     */
    public function checkTrackerReportAuthKey($authKey)
    {
        return (new TorrentDownloadRepository)->checkTrackerReportAuthKey($authKey);
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     */
    public function resetTrackerReportAuthKeySecret($uid, $torrentId = 0): string
    {
        return (new TorrentDownloadRepository)->resetTrackerReportAuthKeySecret($uid, $torrentId);
    }

    public function addPiecesHashCache(int $torrentId, string $piecesHash): bool|int|\Redis
    {
        return (new TorrentDownloadRepository)->addPiecesHashCache($torrentId, $piecesHash);
    }

    public function delPiecesHashCache(string $piecesHash): bool|int|\Redis
    {
        return (new TorrentDownloadRepository)->delPiecesHashCache($piecesHash);
    }

    /**
     * @param  mixed  $piecesHash
     * @return array<int|string, mixed>
     */
    public function getPiecesHashCache($piecesHash): array
    {
        return (new TorrentDownloadRepository)->getPiecesHashCache($piecesHash);
    }

    /**
     * @param  mixed  $id
     * @return array<int|string, mixed>
     */
    public function loadPiecesHashCache($id = 0): array
    {
        return (new TorrentDownloadRepository)->loadPiecesHashCache($id);
    }

    public function touchCacheStamp(int|string $torrentId, string $field = 'cache_stamp'): void
    {
        (new TorrentDownloadRepository)->touchCacheStamp($torrentId, $field);
    }

    public function resetCacheStamp(int|string $torrentId, string $field = 'cache_stamp'): void
    {
        (new TorrentDownloadRepository)->resetCacheStamp($torrentId, $field);
    }

    /**
     * @param  mixed  $user
     * @return array<int|string, mixed>
     */
    public function buildApprovalModal($user, int $torrentId)
    {
        return (new TorrentModerationRepository)->buildApprovalModal($user, $torrentId);
    }

    /**
     * @param  mixed  $user
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    public function approval($user, array $params): array
    {
        return (new TorrentModerationRepository)->approval($user, $params);
    }

    /**
     * @param  mixed  $approvalStatus
     * @param  mixed  $show
     */
    public function renderApprovalStatus($approvalStatus, $show = null): string
    {
        return (new TorrentModerationRepository)->renderApprovalStatus($approvalStatus, $show);
    }

    /** @param  mixed  $approvalStatus */
    public function shouldShowApprovalStatusIcon($approvalStatus): bool
    {
        return (new TorrentModerationRepository)->shouldShowApprovalStatusIcon($approvalStatus);
    }

    public static function getApprovalDenyCount(int $ownerId): int
    {
        return TorrentModerationRepository::getApprovalDenyCount($ownerId);
    }

    /**
     * @return array<string, mixed>|false
     */
    public static function getSnatchInfo(int|string $torrentId, int|string $userId): array|false
    {
        $record = DB::table('snatched')
            ->where('torrentid', (int) $torrentId)
            ->where('userid', (int) $userId)
            ->orderBy('id', 'desc')
            ->first();

        return $record ? (array) $record : false;
    }

    /**
     * @param  mixed  $id
     * @param  array<int|string, mixed>  $tagIdArr
     * @param  mixed  $remove
     * @return mixed
     */
    public function syncTags($id, array $tagIdArr = [], $remove = true)
    {
        return (new TorrentModerationRepository)->syncTags($id, $tagIdArr, $remove);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $posState
     * @param  mixed  $posStateUntil
     */
    public function setPosState($id, $posState, $posStateUntil = null): int
    {
        return (new TorrentModerationRepository)->setPosState($id, $posState, $posStateUntil);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $hrStatus
     */
    public function setHr($id, $hrStatus): int
    {
        return (new TorrentModerationRepository)->setHr($id, $hrStatus);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $spState
     * @param  mixed  $promotionTimeType
     * @param  mixed  $promotionUntil
     */
    public function setSpState($id, $spState, $promotionTimeType, $promotionUntil = null): int
    {
        return (new TorrentModerationRepository)->setSpState($id, $spState, $promotionTimeType, $promotionUntil);
    }

    /**
     * @param  Collection<int, mixed>|\Illuminate\Database\Eloquent\Collection<int, mixed>  $torrents
     * @param  array<int|string, mixed>  $specificSubCategoryAndTags
     */
    public function changeCategory(Collection|\Illuminate\Database\Eloquent\Collection $torrents, int $sectionId, array $specificSubCategoryAndTags): void
    {
        (new TorrentModerationRepository)->changeCategory($torrents, $sectionId, $specificSubCategoryAndTags);
    }

    /**
     * @param  int|int[]  $id
     */
    public function deleteTorrents(int|array $id, bool $notify = false): void
    {
        (new TorrentModerationRepository)->deleteTorrents($id, $notify);
    }
}
