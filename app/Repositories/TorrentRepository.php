<?php

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\ModelEventEnum;
use App\Enums\Permission\PermissionEnum;
use App\Events\TorrentUpdated;
use App\Exceptions\InsufficientPermissionException;
use App\Exceptions\NexusException;
use App\Http\Resources\TorrentResource;
use App\Models\AudioCodec;
use App\Models\Bookmark;
use App\Models\Category;
use App\Models\Codec;
use App\Models\HitAndRun;
use App\Models\Media;
use App\Models\Message;
use App\Models\Peer;
use App\Models\Processing;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\SiteLog;
use App\Models\Snatch;
use App\Models\Source;
use App\Models\StaffMessage;
use App\Models\Standard;
use App\Models\Torrent;
use App\Models\TorrentBuyLog;
use App\Models\TorrentOperationLog;
use App\Models\TorrentSecret;
use App\Models\TorrentTag;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Events;
use App\Support\Hooks;
use App\Support\Logger;
use App\Support\Path;
use App\Support\UserDisplay;
use App\Utils\ApiQueryBuilder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Hashids\Hashids;
use Nexus\Database\NexusDB;

use Rhilip\Bencode\Bencode;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TorrentRepository extends BaseRepository
{
    const BOUGHT_USER_CACHE_KEY_PREFIX = "torrent_purchasers";

    const BUY_FAIL_CACHE_KEY_PREFIX = "torrent_purchase_fails";

    const PIECES_HASH_CACHE_KEY = "torrent_pieces_hash";

    const BUY_STATUS_SUCCESS = 0;
    const BUY_STATUS_NOT_YET = -1;
    const BUY_STATUS_UNKNOWN = -2;

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $defaultLoadRelationships = [
        'basic_category', 'basic_category.search_box',
        'basic_audiocodec', 'basic_codec', 'basic_medium',
        'basic_source', 'basic_processing', 'basic_standard', ];

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $allowIncludes = ['user', 'extra', 'tags'];

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $allowIncludeCounts = ['thank_users', 'reward_logs'];

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $allowIncludeFields = [
        'has_bookmarked', 'has_thanked', 'has_rewarded',
        'description', 'download_url', 'active_status'
    ];

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array  $allowFilters = [
        'title', 'category', 'source', 'medium', 'codec', 'audiocodec', 'standard', 'processing',
        'owner', 'visible', 'added', 'size', 'sp_state', 'leechers', 'seeders', 'times_completed',
        'bookmark',
    ];

    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    /** @var  array<int|string, mixed> */
    private static array $allowSorts = ['id', 'comments', 'size', 'seeders', 'leechers', 'times_completed'];

    /**
     * fetch torrent list
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @param  string  $sectionName
     * @return  mixed
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
            throw new NexusException(\App\Support\Locale::trans("upload.invalid_section", [], null));
        }
        $categoryIdList = $searchBox->categories()->pluck('id')->toArray();
        //query this info default
        $query = Torrent::query()->with(self::$defaultLoadRelationships)
            ->whereIn('category', $categoryIdList)
            ->orderBy("pos_state", "DESC");
        $apiQueryBuilder = ApiQueryBuilder::for(TorrentResource::NAME, $query, $request)
            ->allowIncludes(self::$allowIncludes)
            ->allowIncludeCounts(self::$allowIncludeCounts)
            ->allowIncludeFields(self::$allowIncludeFields)
            ->allowFilters(self::$allowFilters)
            ->allowSorts(self::$allowSorts)
            ->registerCustomFilter('title', function (Builder $query, Request $request) {
                $title = $request->input(ApiQueryBuilder::PARAM_NAME_FILTER.".title");
                $title = trim(str_replace('.', '', $title));
                if ($title) {
                    $titleParts = explode(" ", $title);
                    $keywordCount = 1;
                    foreach ($titleParts as $titlePart) {
                        if ($keywordCount > 3) {
                            break;
                        }
                        $titlePart = trim($titlePart);
                        $query->where(function (Builder $query) use ($titlePart) {
                            $query->where('name', 'like', '%' . $titlePart . '%');
                        });
                        $keywordCount++;
                    }
                }
            })
            ->registerCustomFilter('bookmark', function (Builder $query, Request $request) use ($user) {
                $filterBookmark = $request->input(ApiQueryBuilder::PARAM_NAME_FILTER.".bookmark");
                if ($filterBookmark === Bookmark::FILTER_INCLUDE) {
                    $query->whereHas("bookmarks", function (Builder $query) use ($user) {
                        $query->where("userid", $user->id);
                    });
                } elseif ($filterBookmark === Bookmark::FILTER_EXCLUDE) {
                    $query->whereDoesntHave("bookmarks", function (Builder $query) use ($user) {
                        $query->where("userid", $user->id);
                    });
                }
            })
            ->registerCustomFilter('visible', function (Builder $query, Request $request) {
                $filterVisible = $request->input(ApiQueryBuilder::PARAM_NAME_FILTER.".visible", Torrent::FILTER_VISIBLE_YES);
                if ($filterVisible === Torrent::FILTER_VISIBLE_YES) {
                    $query->where('visible', Torrent::VISIBLE_YES);
                } elseif ($filterVisible === Torrent::FILTER_VISIBLE_NO) {
                    $query->where('visible', Torrent::VISIBLE_NO);
                }
            })
        ;
        $query = $apiQueryBuilder->build();
        if (!$apiQueryBuilder->hasSort() || !$apiQueryBuilder->hasSort('id')) {
            $query->orderBy("id", "DESC");
        }
        \App\Support\Logger::writeWithContext((string) "before query torrent list", (string) 'info', (bool) false);
        $torrents = $query->paginate($this->getPerPageFromRequest($request));
        \App\Support\Logger::writeWithContext((string) "after query torrent list", (string) 'info', (bool) false);
        return $this->appendIncludeFields($apiQueryBuilder, $user, $torrents);
    }

    /**
     * @param  int  $id
     * @param  \App\Models\User  $user
     * @return  mixed
     */
    public function getDetail(int $id, User $user)
    {
        //query this info default
        $query = Torrent::query()->with(self::$defaultLoadRelationships);
        $apiQueryBuilder = ApiQueryBuilder::for(TorrentResource::NAME, $query)
            ->allowIncludes(self::$allowIncludes)
            ->allowIncludeCounts(self::$allowIncludeCounts)
            ->allowIncludeFields(self::$allowIncludeFields)
        ;
        \App\Support\Logger::writeWithContext((string) "before query torrent detail", (string) 'info', (bool) false);
        $torrent = $apiQueryBuilder->build()->findOrFail($id);
        \App\Support\Logger::writeWithContext((string) "before query torrent detail", (string) 'info', (bool) false);
        $torrentList = $this->appendIncludeFields($apiQueryBuilder, $user, [$torrent]);
        return $torrentList[0];
    }

    /**
     * @param  \App\Utils\ApiQueryBuilder  $apiQueryBuilder
     * @param  \App\Models\User  $user
     * @param  mixed  $torrentList
     * @return  mixed
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
            $torrentModule = new \Nexus\Torrent\Torrent();
            $activeData = $torrentModule->listLeechingSeedingStatus($user->id, $torrentIdArr);
        }
        \App\Support\Logger::writeWithContext((string) "after prepare has data", (string) 'info', (bool) false);

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
                $descriptionArr = \App\Support\Description::parse($torrent->extra->descr ?? '');
                $torrent->description = $descriptionArr;
                $torrent->images = \App\Support\Description::imageFromDescription($descriptionArr);
            }
            if ($apiQueryBuilder->hasIncludeField("download_url")) {
                $torrent->download_url = $this->getDownloadUrl($id, $user);
            }
        }
        \App\Support\Logger::writeWithContext((string) "after fill has data", (string) 'info', (bool) false);
        return $torrentList;
    }

    /**
     * @param  mixed  $id
     * @param  array<int|string, mixed>|\App\Models\User  $user
     */
    public function getDownloadUrl($id, array|User $user): string
    {
        return sprintf(
            '%s/download.php?downhash=%s.%s',
            \App\Support\Url::schemeAndHost(false), is_array($user) ? $user['id'] : $user->id, $this->encryptDownHash($id, $user)
        );
    }

    /**
     * @param  ?int  $id
     * @return  mixed
     */
    public function getSearchBox(?int $id = null)
    {
        if (is_null($id)) {
            $id = \App\Support\Config\SiteConfig::current()->main->browseCat();
        }
        $searchBox = SearchBox::query()->findOrFail((int)$id);
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
     * @return  mixed
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
     * @return  array<int|string, mixed>
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
        if ($peers->has(Peer::SEEDER_YES)) {
            $seederList = $peers->get(Peer::SEEDER_YES)->sort(function ($a, $b) {
                $x = $a->uploaded;
                $y = $b->uploaded;
                if ($x == $y)
                    return 0;
                if ($x < $y)
                    return 1;
                return -1;
            });
            $seederList = $this->formatPeers($seederList);
        }
        if ($peers->has(Peer::SEEDER_NO)) {
            $leecherList = $peers->get(Peer::SEEDER_NO)->sort(function ($a, $b) {
                $x = $a->to_go;
                $y = $b->to_go;
                if ($x == $y)
                    return 0;
                if ($x < $y)
                    return -1;
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
        return \App\Support\Format::size($diff / $seconds) . '/s';
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
        return \App\Support\Format::size($diff / $seconds) . '/s';
    }

    /** @param  mixed  $peer */
    public function getDownloadProgress($peer): string
    {
        return sprintf("%.2f%%", 100 * (1 - ($peer->to_go / $peer->relative_torrent->size)));
    }

    /**
     * @param  mixed  $peer
     * @return  mixed
     */
    public function getShareRatio($peer)
    {
        if ($peer->downloaded) {
            $ratio = floor(($peer->uploaded / $peer->downloaded) * 1000) / 1000;
        } elseif ($peer->uploaded) {
            //@todo 读语言文件
            $ratio = '无限';
        } else {
            $ratio = '---';
        }
        return $ratio;
    }

    /**
     * @param  mixed  $peers
     * @return  mixed
     */
    private function formatPeers($peers)
    {
        foreach ($peers as &$item) {
            $item->upload_text = sprintf('%s@%s', \App\Support\Format::size($item->uploaded), $this->getPeerUploadSpeed($item));
            $item->download_text = sprintf('%s@%s', \App\Support\Format::size($item->downloaded), $this->getPeerDownloadSpeed($item));
            $item->download_progress = $this->getDownloadProgress($item);
            $item->share_ratio = $this->getShareRatio($item);
            $item->connect_time_total = $item->started->diffForHumans();
            $item->last_action_human = $item->last_action->diffForHumans();
            $item->agent_human = htmlspecialchars(\App\Support\Strings::userAgentClient( $item->agent));
        }
        return $peers;
    }


    /**
     * @param  mixed  $torrentId
     * @return  mixed
     */
    public function listSnatches($torrentId)
    {
        $snatches = Snatch::query()
            ->where('torrentid', $torrentId)
            ->where('finished', Snatch::FINISHED_YES)
            ->with(['user'])
            ->orderBy('completedat', 'desc')
            ->paginate();
        return $snatches;
    }

    /**
     * @param  mixed  $snatch
     * @return  mixed
     */
    public function getSnatchUploadSpeed($snatch)
    {
        if ($snatch->seedtime <= 0) {
            $speed = \App\Support\Format::size(0);
        } else {
            $speed = \App\Support\Format::size($snatch->uploaded / ($snatch->seedtime + $snatch->leechtime));
        }
        return "$speed/s";
    }

    /**
     * @param  mixed  $snatch
     * @return  mixed
     */
    public function getSnatchDownloadSpeed($snatch)
    {
        if ($snatch->leechtime <= 0) {
            $speed = \App\Support\Format::size(0);
        } else {
            $speed = \App\Support\Format::size($snatch->downloaded / $snatch->leechtime);
        }
        return "$speed/s";
    }

    /** @var array<string, string> */
    private static array $downHashKeys = [];

    /**
     * @param  mixed  $id
     * @param  mixed  $user
     */
    public function encryptDownHash($id, $user): string
    {
        $userInfo = $this->getUserForDownHash($user);
        $key = $this->getHkdfDownHashKey($userInfo['id'], $userInfo['passkey']);
        $payload = [
            'id' => $id,
            'exp' => time() + 3600,
        ];

        return JWT::encode($payload, $key, 'HS256');
    }

    /**
     * @param  mixed  $downHash
     * @param  mixed  $user
     * @return  array<int|string, mixed>
     */
    public function decryptDownHash($downHash, $user)
    {
        $userInfo = $this->getUserForDownHash($user);
        $keys = $this->buildDownHashVerificationKeys($userInfo['id'], $userInfo['passkey']);

        foreach ($keys as $key) {
            try {
                $decoded = JWT::decode($downHash, new Key($key, 'HS256'));

                return [$decoded->id];
            } catch (\Exception $e) {
                continue;
            }
        }

        \App\Support\Logger::write("Invalid down hash: $downHash", "error");

        return [];
    }

    /**
     * @param  mixed  $user
     * @return  array{id: int, passkey: string}
     */
    private function getUserForDownHash($user): array
    {
        $passkey = '';
        if ($user instanceof User && $user->passkey) {
            $passkey = $user->passkey;
            $id = (int) $user->id;
        } elseif (is_array($user) && !empty($user['passkey'])) {
            $passkey = $user['passkey'];
            $id = (int) $user['id'];
        } elseif (is_scalar($user)) {
            $user = User::query()->findOrFail(intval($user), ['id', 'passkey']);
            $passkey = $user->passkey;
            $id = (int) $user->id;
        } else {
            throw new \InvalidArgumentException("Invalid user: " . json_encode($user));
        }

        if (empty($passkey)) {
            throw new \InvalidArgumentException("Invalid user: " . json_encode($user));
        }

        return ['id' => $id, 'passkey' => (string) $passkey];
    }

    /**
     * @return array<int, string>
     */
    private function buildDownHashVerificationKeys(int $userId, string $passkey): array
    {
        $keys = [$this->getHkdfDownHashKey($userId, $passkey)];

        // Legacy md5-based keys are still accepted until the user changes their
        // passkey; the old key material includes the passkey, so a passkey
        // rotation automatically invalidates any previously issued md5 downhash.
        $now = time();
        foreach ([$now, $now - 86400, $now - 2 * 86400] as $ts) {
            $keys[] = $this->getLegacyMd5DownHashKey($userId, $passkey, date('Ymd', $ts));
        }

        return $keys;
    }

    private function getHkdfDownHashKey(int $userId, string $passkey): string
    {
        $cacheKey = $userId . ':' . $passkey;
        if (isset(self::$downHashKeys[$cacheKey])) {
            return self::$downHashKeys[$cacheKey];
        }

        $appKey = (string) \App\Support\Env::get('APP_KEY', '');
        if ($appKey === '') {
            throw new \RuntimeException('APP_KEY is not configured for downhash');
        }

        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        return self::$downHashKeys[$cacheKey] = hash_hkdf('sha256', $appKey, 32, 'nexus-downhash-' . $userId . '-' . $passkey);
    }

    private function getLegacyMd5DownHashKey(int $userId, string $passkey, string $dateYmd): string
    {
        return md5($passkey . $dateYmd . $userId);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $uid
     * @param  mixed  $initializeIfNotExists
     * @return  string
     * @deprecated
     * @throws NexusException
     */
    public function getTrackerReportAuthKey($id, $uid, $initializeIfNotExists = false): string
    {
        $key = $this->getTrackerReportAuthKeySecret($id, $uid, $initializeIfNotExists);
        $hash = (new Hashids($key))->encode(date('Ymd'));
        return sprintf('%s|%s|%s', $id, $uid, $hash);
    }

    /**
     * check tracker report authkey
     * if valid, the result will be the date the key generate, else if will be empty string
     * @param  mixed  $authKey
     * @return  array<int|string, mixed>
     * @deprecated
     * @date 2021/6/3
     * @time 20:29
     * @throws NexusException
     */
    public function checkTrackerReportAuthKey($authKey)
    {
        $arr = explode('|', $authKey);
        if (count($arr) != 3) {
            throw new NexusException('Invalid authkey');
        }
        $id = $arr[0];
        $uid = $arr[1];
        $hash = $arr[2];
        $key = $this->getTrackerReportAuthKeySecret($id, $uid);
        return (new Hashids($key))->decode($hash);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $uid
     * @param  mixed  $initializeIfNotExists
     * @return  mixed
     */
    private function getTrackerReportAuthKeySecret($id, $uid, $initializeIfNotExists = false)
    {
        $secret = NexusDB::remember("torrent_secret_{$uid}_{$id}", 3600, function () use ($id, $uid) {
            return TorrentSecret::query()
                ->where('uid', $uid)
                ->whereIn('torrent_id', [0, $id])
                ->orderBy('torrent_id', 'desc')
                ->orderBy('id', 'desc')
                ->first();
        });

        if ($secret) {
            return $secret->secret;
        }
        if ($initializeIfNotExists) {
            $insert = [
                'uid' => $uid,
                'torrent_id' => 0,
                'secret' => Str::random(),
            ];
            \App\Support\Logger::writeWithContext((string) ("[INSERT_TORRENT_SECRET] " . json_encode($insert)), (string) 'info', (bool) false);
            TorrentSecret::query()->insert($insert);
            return $insert['secret'];
        }
        throw new NexusException('No valid report secret, please re-download this torrent.');
    }

    /**
     * reset user tracker report authkey secret
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @return  string
     * @todo wrap with transaction
     * @date 2021/6/3
     * @time 20:15
     */
    public function resetTrackerReportAuthKeySecret($uid, $torrentId = 0): string
    {
        $insert = [
            'uid' => $uid,
            'secret' => Str::random(),
            'torrent_id' => $torrentId,
        ];
        if ($torrentId > 0) {
            TorrentSecret::query()->insert($insert);
            return $insert['secret'];
        }

        TorrentSecret::query()->where('uid', $uid)->delete();
        TorrentSecret::query()->insert($insert);
        return $insert['secret'];

    }

    /**
     * @param  mixed  $user
     * @param  int  $torrentId
     * @return  array<int|string, mixed>
     */
    public function buildApprovalModal($user, int $torrentId)
    {
        $user = $this->getUser($user);
        Permission::assertCan(PermissionEnum::TORRENT_APPROVAL, $user);
        $torrent = Torrent::query()->findOrFail($torrentId, ['id', 'approval_status', 'banned']);
        $radios = [];
        foreach (Torrent::$approvalStatus as $key => $value) {
            if ($torrent->approval_status == $key) {
                $checked = " checked";
            } else {
                $checked = "";
            }
            $radios[] = sprintf(
                '<label><input type="radio" name="params[approval_status]" value="%s"%s>%s</label>',
                $key, $checked, \App\Support\Locale::trans("torrent.approval.status_text.{$key}", [], null)
            );
        }
        $id = "torrent-approval";
        $rows = [];
        $rowStyle = "display: flex; padding: 10px; align-items: center";
        $labelStyle = "width: 80px";
        $formId = "$id-form";
        $rows[] = sprintf(
            '<div class="%s-row" style="%s"><div style="%s">%s: </div><div>%s</div></div>',
            $id, $rowStyle, $labelStyle, \App\Support\Locale::trans('torrent.approval.status_label', [], null), implode('', $radios)
        );
        $rows[] = sprintf(
            '<div class="%s-row" style="%s"><div style="%s">%s: </div><div><textarea name="params[comment]" rows="4" cols="40"></textarea></div></div>',
            $id, $rowStyle, $labelStyle, \App\Support\Locale::trans('torrent.approval.comment_label', [], null)
        );
        $rows[] = sprintf('<input type="hidden" name="params[torrent_id]" value="%s" />', $torrent->id);

        $html = sprintf('<div id="%s-box" style="padding: 15px 30px"><form id="%s">%s</form></div>', $id, $formId, implode('', $rows));

        return [
            'id' => $id,
            'form_id' => $formId,
            'title' => \App\Support\Locale::trans('torrent.approval.modal_title', [], null),
            'content' => $html,
        ];

    }

    /**
     * @param  mixed  $user
     * @param  array<int|string, mixed>  $params
     * @return  array<int|string, mixed>
     */
    public function approval($user, array $params): array
    {
        $user = $this->getUser($user);
        Permission::assertCan(PermissionEnum::TORRENT_APPROVAL, $user);
        $torrentId = (int) $params['torrent_id'];
        $approvalStatus = (int) $params['approval_status'];
        $comment = (string) ($params['comment'] ?? '');
        $torrent = Torrent::query()->findOrFail($torrentId, Torrent::$commentFields);
        $lastLog = TorrentOperationLog::query()
            ->where('torrent_id', $torrentId)
            ->where('uid', $user->id)
            ->orderBy('id', 'desc')
            ->first();
        if ($torrent->approval_status == $approvalStatus && $lastLog && $lastLog->comment == $comment) {
            //No change
            return $params;
        }
        $torrentUpdate = $torrentOperationLog = [];
        $torrentUpdate['approval_status'] = $approvalStatus;
        $notifyUser = false;
        if ($approvalStatus == Torrent::APPROVAL_STATUS_ALLOW) {
            $torrentUpdate['banned'] = 'no';
            $torrentUpdate['visible'] = 'yes';
            if ($torrent->approval_status != $approvalStatus) {
                $torrentOperationLog['action_type'] = TorrentOperationLog::ACTION_TYPE_APPROVAL_ALLOW;
                //increase promotion time
                if (
                    !\App\Support\Config\SiteConfig::current()->torrent->approvalStatusNoneVisible()
                    && $torrent->sp_state != Torrent::PROMOTION_NORMAL
                    && $torrent->promotion_until
                ) {
                    $hasBeenDownloaded = Snatch::query()->where('torrentid', $torrent->id)->exists();
                    $log = "Torrent: {$torrent->id} is in promotion, hasBeenDownloaded: $hasBeenDownloaded";
                    if (!$hasBeenDownloaded) {
                        $diffInSeconds = $torrent->promotion_until->diffInSeconds($torrent->added, true);
                        $log .= ", addSeconds: $diffInSeconds";
                        $torrentUpdate['promotion_until'] = $torrent->promotion_until->addSeconds($diffInSeconds);
                    }
                    \App\Support\Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
                }
            }
            if ($torrent->approval_status == Torrent::APPROVAL_STATUS_DENY) {
                $notifyUser = true;
            }
        } elseif ($approvalStatus == Torrent::APPROVAL_STATUS_DENY) {
            $torrentUpdate['banned'] = 'yes';
            $torrentUpdate['visible'] = 'no';
            //Deny, record and notify all the time
            $torrentOperationLog['action_type'] = TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY;
            $notifyUser = true;
        } elseif ($approvalStatus == Torrent::APPROVAL_STATUS_NONE) {
            $torrentUpdate['banned'] = 'no';
            $torrentUpdate['visible'] = 'yes';
            if ($torrent->approval_status != $approvalStatus) {
                $torrentOperationLog['action_type'] = TorrentOperationLog::ACTION_TYPE_APPROVAL_NONE;
            }
            if ($torrent->approval_status == Torrent::APPROVAL_STATUS_DENY) {
                $notifyUser = true;
            }
        } else {
            throw new \InvalidArgumentException("Invalid approval_status: " . $approvalStatus);
        }

        if (isset($torrentOperationLog['action_type'])) {
            $torrentOperationLog['uid'] = $user->id;
            $torrentOperationLog['torrent_id'] = $torrent->id;
            $torrentOperationLog['comment'] = $comment;
        }

        NexusDB::transaction(function () use ($torrent, $torrentOperationLog, $torrentUpdate, $notifyUser) {
            $log = "torrent: " . $torrent->id;
            /** @var array<string, mixed> $torrentUpdate */
            $log .= ", [UPDATE_TORRENT]: " . \App\Support\Json::encode($torrentUpdate);
            $torrent->update($torrentUpdate);
            if (!empty($torrentOperationLog)) {
                $log .= ", [ADD_TORRENT_OPERATION_LOG]: " . \App\Support\Json::encode($torrentOperationLog);
                TorrentOperationLog::add($torrentOperationLog, $notifyUser);
            }
            \App\Support\Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
        });

        return $params;

    }

    /**
     * @param  mixed  $approvalStatus
     * @param  mixed  $show
     */
    public function renderApprovalStatus($approvalStatus, $show = null): string
    {
        if ($show === null) {
            $show = $this->shouldShowApprovalStatusIcon($approvalStatus);
        }
        if ($show) {
            return sprintf(
                '<span style="margin-left: 6px" title="%s">%s</span>',
                \App\Support\Locale::trans("torrent.approval.status_text.{$approvalStatus}", [], null),
                \App\Models\Torrent::$approvalStatus[$approvalStatus]['icon']
            );
        }
        return '';
    }

    /** @param  mixed  $approvalStatus */
    public function shouldShowApprovalStatusIcon($approvalStatus): bool
    {
        if (\App\Support\Config\SiteConfig::current()->torrent->approvalStatusIconEnabled()) {
            //启用审核状态图标，肯定显示
            return true;
        }
        if (
            $approvalStatus != \App\Models\Torrent::APPROVAL_STATUS_ALLOW
            && !\App\Support\Config\SiteConfig::current()->torrent->approvalStatusNoneVisible()
        ) {
            //不启用审核状态图标，尽量不显示。在种子不是审核通过状态，而审核不通过又不能被用户看到时，显示
            return true;
        }
        return false;
    }

    /**
     * @param  mixed  $id
     * @param  array<int|string, mixed>  $tagIdArr
     * @param  mixed  $remove
     * @return  mixed
     */
    public function syncTags($id, array $tagIdArr = [], $remove = true)
    {
        Permission::assertCan(PermissionEnum::TORRENT_MANAGE);
        $idArr = Arr::wrap($id);
        return NexusDB::transaction(function () use ($idArr, $tagIdArr, $remove) {
            $sql = "insert into torrent_tags (torrent_id, tag_id, created_at, updated_at) values ";
            $time = now()->toDateTimeString();
            $values = [];
            foreach ($idArr as $torrentId) {
                foreach ($tagIdArr as $tagId) {
                    $values[] = sprintf("(%s, %s, '%s', '%s')", $torrentId, $tagId, $time, $time);
                }
            }
            $sql .= implode(', ', $values) . " " . NexusDB::upsertField(['torrent_id', 'tag_id'], ['updated_at']);
            if ($remove) {
                TorrentTag::query()->whereIn('torrent_id', $idArr)->delete();
            }
            if (!empty($values)) {
                DB::insert($sql);
            }
            return count($values);
        });

    }

    /**
     * @param  mixed  $id
     * @param  mixed  $posState
     * @param  mixed  $posStateUntil
     */
    public function setPosState($id, $posState, $posStateUntil = null): int
    {
        Permission::assertCan(PermissionEnum::TORRENT_SET_STICKY);
        if ($posState == Torrent::POS_STATE_STICKY_NONE) {
            $posStateUntil = null;
        }
        if ($posStateUntil && Carbon::parse($posStateUntil)->lte(now())) {
            $posState = Torrent::POS_STATE_STICKY_NONE;
            $posStateUntil = null;
        }
        $update = [
            'pos_state' => $posState,
            'pos_state_until' => $posStateUntil,
        ];
        $idArr = Arr::wrap($id);
        return Torrent::query()->whereIn('id', $idArr)->update($update);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $hrStatus
     */
    public function setHr($id, $hrStatus): int
    {
        Permission::assertCan(PermissionEnum::TORRENT_MANAGE);
        if (!isset(Torrent::$hrStatus[$hrStatus])) {
            throw new \InvalidArgumentException("Invalid hrStatus: $hrStatus");
        }
        $update = [
            'hr' => $hrStatus,
        ];
        $idArr = Arr::wrap($id);
        \App\Support\Logger::writeWithContext((string) sprintf("set torrent: %s hr: %s", implode(",", $idArr), $hrStatus), (string) 'info', (bool) false);
        return Torrent::query()->whereIn('id', $idArr)->update($update);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $spState
     * @param  mixed  $promotionTimeType
     * @param  mixed  $promotionUntil
     */
    public function setSpState($id, $spState, $promotionTimeType, $promotionUntil = null): int
    {
        Permission::assertCan(PermissionEnum::TORRENT_ON_PROMOTION);
        if (!isset(Torrent::$promotionTypes[$spState])) {
            throw new \InvalidArgumentException("Invalid spState: $spState");
        }
        if (!isset(Torrent::$promotionTimeTypes[$promotionTimeType])) {
            throw new \InvalidArgumentException("Invalid promotionTimeType: $promotionTimeType");
        }
        if (in_array($promotionTimeType, [Torrent::PROMOTION_TIME_TYPE_GLOBAL, Torrent::PROMOTION_TIME_TYPE_PERMANENT])) {
            $promotionUntil = null;
        } elseif (!$promotionUntil || Carbon::parse($promotionUntil)->lte(now())) {
            throw new \InvalidArgumentException("Invalid promotionUntil: $promotionUntil");
        }
        $update = [
            'sp_state' => $spState,
            'promotion_time_type' => $promotionTimeType,
            'promotion_until' => $promotionUntil,
        ];
        $idArr = Arr::wrap($id);
        return Torrent::query()->whereIn('id', $idArr)->update($update);
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
            $idAttr = $btnId ? ' id="' . htmlspecialchars($btnId, ENT_QUOTES, 'UTF-8') . '"' : '';
            $onClickAttr = $btnOnClick ? ' onclick="' . htmlspecialchars($btnOnClick, ENT_QUOTES, 'UTF-8') . '"' : '';
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
     * @param  array<int|string, mixed>  $torrentInfo
     * @param  mixed  $size
     * @param  mixed  $verticalAlign
     * @return  mixed
     */
    public function getPaidIcon(array $torrentInfo, $size = 16, $verticalAlign = 'sub')
    {
        if (!isset($torrentInfo['price']) || $torrentInfo['price'] <= 0) {
            return '';
        }
        return sprintf('<span title="%s" style="vertical-align: %s"><svg t="1676058062789" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="3406" width="%s" height="%s"><path d="M554.666667 810.666667v42.666666h-85.333334v-42.666666c-93.866667 0-170.666667-76.8-170.666666-170.666667h85.333333c0 46.933333 38.4 85.333333 85.333333 85.333333v-170.666666c-93.866667 0-170.666667-76.8-170.666666-170.666667s76.8-170.666667 170.666666-170.666667V170.666667h85.333334v42.666666c93.866667 0 170.666667 76.8 170.666666 170.666667h-85.333333c0-46.933333-38.4-85.333333-85.333333-85.333333v170.666666h17.066666c29.866667 0 68.266667 17.066667 98.133334 42.666667 34.133333 29.866667 59.733333 76.8 59.733333 128-4.266667 93.866667-81.066667 170.666667-174.933333 170.666667z m0-85.333334c46.933333 0 85.333333-38.4 85.333333-85.333333s-38.4-85.333333-85.333333-85.333333v170.666666zM469.333333 298.666667c-46.933333 0-85.333333 38.4-85.333333 85.333333s38.4 85.333333 85.333333 85.333333V298.666667z" fill="#CD7F32" p-id="3407"></path></svg></span>', \App\Support\Locale::trans('torrent.paid_torrent', [], null), $verticalAlign, $size, $size);
    }

    /** @param  mixed  $torrentId */
    public function loadBoughtUser($torrentId): int
    {
        $size = 500;
        $page = 1;
        $redis = NexusDB::redis();
        $total = 0;
        while (true) {
            $list = TorrentBuyLog::query()->where("torrent_id", $torrentId)->forPage($page, $size)->get(['torrent_id', 'uid']);
            if ($list->isEmpty()) {
                break;
            }
            foreach ($list as $item) {
                $key = $this->getBoughtUserCacheKey($torrentId, $item->uid);
                $redis->set($key, 1, ['EX' => 86400*30]);
                $total += 1;
                \App\Support\Logger::writeWithContext((string) sprintf("set %s 1", $key), (string) 'info', (bool) false);
            }
            $page++;
        }
        \App\Support\Logger::writeWithContext((string) "torrent_purchasers:{$torrentId} LOAD DONE, total: {$total}", (string) 'info', (bool) false);
        return $total;
    }

    /**
     * 购买成功，缓存 30 天并更新到 snatched 上
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @param  mixed  $buyLogId
     * @return  void
     * @throws \RedisException
     */
    public function addBuySuccessCache($uid, $torrentId, $buyLogId): void
    {
        NexusDB::redis()->set($this->getBoughtUserCacheKey($torrentId, $uid), 1, ['NX', 'EX' => 86400*30]);
        $record = Snatch::query()
            ->where("torrentid", $torrentId)
            ->where("userid", $uid)
            ->first();
        if ($record) {
            $record->buy_log_id = $buyLogId;
            $record->save();
            \App\Support\Events::publishModel(ModelEventEnum::SNATCHED_UPDATED, $record->id, "");
        } else {
            \App\Support\Logger::writeWithContext((string) "addBuySuccessCache, uid: {$uid}, torrentId: {$torrentId}, buyLogId: {$buyLogId}, snatched not exists", (string) 'error', (bool) false);
        }

    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     */
    public function hasBuySuccessCache($uid, $torrentId): bool
    {
        $key = $this->getBoughtUserCacheKey($torrentId, $uid);
        if (NexusDB::redis()->exists($key)) {
            return true;
        }
        return false;
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     */
    public function hasBuySuccess($uid, $torrentId): bool
    {
        if ($this->hasBuySuccessCache($uid, $torrentId)) {
            return true;
        }
        $buyLog = TorrentBuyLog::query()
            ->where("torrent_id", $torrentId)
            ->where("uid", $uid)
            ->first();
        if ($buyLog) {
            $this->addBuySuccessCache($uid, $torrentId, $buyLog->id);
        }
        return $buyLog != null;
    }

    /**
     * 获取购买种子的缓存状态
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @return  int
     */
    public function getBuyStatus($uid, $torrentId): int
    {
        //从缓存中判断是否购买过
        if ($this->hasBuySuccess($uid, $torrentId)) {
            return self::BUY_STATUS_SUCCESS;
        }
        //是否购买失败过
        $buyFailCount = $this->getBuyFailCache($uid, $torrentId);
        if ($buyFailCount > 0) {
            //根据失败次数，禁用下载权限并做提示等
            return $buyFailCount;
        }
        //不是成功或失败，直接返回未知
        return self::BUY_STATUS_UNKNOWN;
    }

    /**
     * 添加购买失败缓存, 结果累加
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @return  void
     * @throws \RedisException
     */
    public function addBuyFailCache($uid, $torrentId): void
    {
        $key = $this->getBuyFailCacheKey($uid, $torrentId);
        $result = NexusDB::redis()->incr($key);
        if ($result == 1) {
            NexusDB::redis()->expire($key, 3600);
        }
    }

    /**
     * 获取失败缓存 ，结果是失败的次数
     * @param  mixed  $uid
     * @param  mixed  $torrentId
     * @return  int
     * @throws \RedisException
     */
    public function getBuyFailCache($uid, $torrentId): int
    {
        return intval(NexusDB::redis()->get($this->getBuyFailCacheKey($uid, $torrentId)));
    }

    /**
     * 购买成功缓存 key
     * @param  mixed  $torrentId
     * @param  mixed  $userId
     * @return  string
     * @update 改为使用字符串判断键是否存在即可
     */
    public function getBoughtUserCacheKey($torrentId, $userId): string
    {
        return  sprintf("%s:%s:%s", self::BOUGHT_USER_CACHE_KEY_PREFIX, $torrentId, $userId);
    }

    /**
     * 购买失败缓存 key
     * @param  int  $userId
     * @param  int  $torrentId
     * @return  string
     */
    public function getBuyFailCacheKey(int $userId, int $torrentId): string
    {
        return sprintf("%s:%s:%s", self::BUY_FAIL_CACHE_KEY_PREFIX, $userId, $torrentId);
    }

    /**
     * @param  int  $torrentId
     * @param  string  $piecesHash
     */
    public function addPiecesHashCache(int $torrentId, string $piecesHash): bool|int|\Redis
    {
        $value = $this->buildPiecesHashCacheValue($torrentId, $piecesHash);
        return NexusDB::redis()->hSet(self::PIECES_HASH_CACHE_KEY, $piecesHash, $value);
    }

    /**
     * @param  int  $torrentId
     * @param  string  $piecesHash
     */
    private  function buildPiecesHashCacheValue(int $torrentId, string $piecesHash): bool|string
    {
        return  json_encode(['torrent_id' => $torrentId, 'pieces_hash' => $piecesHash]);
    }

    /** @param  string  $piecesHash */
    public function delPiecesHashCache(string $piecesHash): bool|int|\Redis
    {
        return NexusDB::redis()->hDel(self::PIECES_HASH_CACHE_KEY, $piecesHash);
    }

    /**
     * @param  mixed  $piecesHash
     * @return  array<int|string, mixed>
     */
    public function getPiecesHashCache($piecesHash): array
    {
        if (!is_array($piecesHash)) {
            $piecesHash = [$piecesHash];
        }
        $maxCount = 100;
        if (count($piecesHash) > $maxCount) {
            throw new \InvalidArgumentException("too many pieces hash, must less then $maxCount");
        }
        $pipe = NexusDB::redis()->multi(\Redis::PIPELINE);
        foreach ($piecesHash as $hash) {
            $pipe->hGet(self::PIECES_HASH_CACHE_KEY, $hash);
        }
        $results = $pipe->exec();
        $logPrefix = sprintf("piecesHashCount: %s, resultCount: %s", count($piecesHash), count($results));
        $out = [];
        foreach ($results as $item) {
            $arr = json_decode($item, true);
            if (is_array($arr) && isset($arr['torrent_id'], $arr['pieces_hash'])) {
                $out[$arr['pieces_hash']] = $arr['torrent_id'];
            } else {
                \App\Support\Logger::writeWithContext((string) sprintf("%s, invalid item: %s(%s)", $logPrefix, var_export($item, true), gettype($item)), (string) 'info', (bool) false);
            }
        }
        return $out;
    }

    /**
     * @param  mixed  $id
     * @return  array<int|string, mixed>
     */
    public function loadPiecesHashCache($id = 0): array
    {
        $page = 1;
        $size = 1000;
        $query = Torrent::query();
        if ($id) {
            $query = $query->whereIn("id", Arr::wrap($id));
        }
        $total = $success = 0;
        $torrentDir = sprintf(
            "%s/%s/",
            rtrim(ROOT_PATH, '/'),
            rtrim(\App\Support\Config\SiteConfig::current()->main->torrentDir(), '/')
        );
        while (true) {
            $list = (clone $query)->forPage($page, $size)->get(['id', 'pieces_hash']);
            if ($list->isEmpty()) {
                \App\Support\Logger::writeWithContext((string) "page: {$page}, size: {$size}, no more data...", (string) 'info', (bool) false);
                break;
            }
            $pipe = NexusDB::redis()->multi(\Redis::PIPELINE);
            $piecesHashRows = [];
            $currentCount = 0;
            foreach ($list as $item) {
                $total++;
                try {
                    $piecesHash = $item->pieces_hash;
                    if (!$piecesHash) {
                        $torrentFile = $torrentDir . $item->id . ".torrent";
                        $loadResult = Bencode::load($torrentFile);
                        $piecesHash = sha1($loadResult['info']['pieces']);
                        $piecesHashRows[] = [
                            'id' => (int) $item->id,
                            'pieces_hash' => $piecesHash,
                        ];
                        \App\Support\Logger::writeWithContext((string) sprintf("torrent: %s no pieces hash, load from torrent file: %s, pieces hash: %s", $item->id, $torrentFile, $piecesHash), (string) 'info', (bool) false);
                    }
                    $pipe->hSet(self::PIECES_HASH_CACHE_KEY, $piecesHash, $this->buildPiecesHashCacheValue($item->id, $piecesHash));
                    $success++;
                    $currentCount++;
                } catch (\Exception $exception) {
                    \App\Support\Logger::writeWithContext((string) sprintf("load pieces hash of torrent: %s error: %s", $item->id, $exception->getMessage()), (string) 'error', (bool) false);
                }
            }
            $pipe->exec();
            if (!empty($piecesHashRows)) {
                NexusDB::table('torrents')->upsert($piecesHashRows, ['id'], ['pieces_hash']);
            }
            \App\Support\Logger::writeWithContext((string) "success load page: {$page}, size: {$size}, count: {$currentCount}", (string) 'info', (bool) false);
            $page++;
        }
        \App\Support\Logger::writeWithContext((string) "[DONE], total: {$total}, success: {$success}", (string) 'info', (bool) false);
        return compact('total', 'success');
    }


    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $torrents
     * @param  int  $sectionId
     * @param  array<int|string, mixed>  $specificSubCategoryAndTags
     * @return  void
     */
    public function changeCategory(Collection $torrents, int $sectionId, array $specificSubCategoryAndTags): void
    {
        \App\Support\Permissions::assertHasPermission(Permission::canManageTorrent());
        $torrentIdArr = $torrents->pluck('id')->toArray();
        if (empty($torrentIdArr)) {
            \App\Support\Logger::writeWithContext((string) "torrents is empty", (string) 'warn', (bool) false);
            return;
        }
        $torrentIdStr = implode(',', $torrentIdArr);
        \App\Support\Logger::writeWithContext((string) "torrentIdStr: {$torrentIdStr}, sectionId: {$sectionId}", (string) 'info', (bool) false);
        $searchBoxRep = new SearchBoxRepository();
        $sections = $searchBoxRep->listSections(SearchBox::listAllSectionId(), true)->keyBy('id');
        if (!$sections->has($sectionId)) {
            throw new NexusException(\App\Support\Locale::trans('upload.invalid_section', [], null));
        }
        $section = $sections->get($sectionId);
        if (!$section instanceof SearchBox) {
            throw new NexusException(\App\Support\Locale::trans('upload.invalid_section', [], null));
        }
        $validCategoryIdArr = $section->categories->pluck('id')->toArray();
        if (!empty($specificSubCategoryAndTags['category']) && !in_array($specificSubCategoryAndTags['category'], $validCategoryIdArr)) {
            throw new NexusException(\App\Support\Locale::trans('upload.invalid_category', [], null));
        }
        $categoryId = (int) ($specificSubCategoryAndTags['category'] ?? 0);
        $category = Category::query()->find($categoryId);
        if (!$category instanceof Category) {
            $category = null;
        }
        $baseUpdateQuery = Torrent::query()->whereIn('id', $torrentIdArr);
        $updateCategoryQuery = $baseUpdateQuery->clone();
        if (!empty($validCategoryIdArr)) {
            $updateCategoryQuery->whereNotIn('category', $validCategoryIdArr);
        }
        $updateCategoryResult = $updateCategoryQuery->update(['category' => 0]);
        \App\Support\Logger::writeWithContext((string) sprintf("update category = 0 when category not in: %s, result: %s", implode(', ', $validCategoryIdArr), $updateCategoryResult), (string) 'info', (bool) false);

        foreach (SearchBox::$taxonomies as $name => $info) {
            $relationName = "taxonomy_{$name}";
            $relation = $section->{$relationName};
            if (empty($specificSubCategoryAndTags[$name])) {
                continue;
            }
            //有指定，看是否有效
            if (!$relation) {
                \App\Support\Logger::writeWithContext((string) "searchBox: {$section->id} no relation of {$name}", (string) 'info', (bool) false);
                throw new NexusException(\App\Support\Locale::trans('upload.not_supported_sub_category_field', ['field' => $name], null));
            }
            $validIdArr = $relation->pluck('id')->toArray();
            $taxonomyId = (int) $specificSubCategoryAndTags[$name];
            if (!in_array($taxonomyId, $validIdArr)) {
                \App\Support\Logger::writeWithContext((string) ("taxonomy {$name}, specific: {$taxonomyId} not in validIdArr: " . implode(', ', $validIdArr)), (string) 'info', (bool) false);
                throw new NexusException(\App\Support\Locale::trans('upload.not_supported_sub_category_field', ['field' => $name], null));
            }

        }
        $operatorId = \App\Support\UserDisplay::currentId();
        $siteLogArr = [];
        foreach ($torrents as $torrent) {
            $siteLogArr[] = [
                'added' => now(),
                'txt' => sprintf("torrent: %s category was set to: %s(%s)", $torrent->id, $category ? $category->name : 'unknown', $category ? $category->id : 0),
                'uid' => $operatorId,
            ];
        }
        NexusDB::transaction(function () use ($torrentIdArr, $categoryId, $siteLogArr) {
            SiteLog::query()->insert($siteLogArr);
            Torrent::query()->whereIn('id', $torrentIdArr)->update(['category' => $categoryId]);
        });
        foreach ($torrents as $torrent) {
            \App\Support\Events::fire(ModelEventEnum::TORRENT_UPDATED, $torrent, null);
        }
        \App\Support\Logger::writeWithContext((string) ("success change to section {$sectionId}, torrent count:" . $torrents->count()), (string) 'info', (bool) false);
    }

    /**
     * Get the latest comment for a torrent, or null if none exists.
     *
     * @return  array<string, mixed>|null
     */
    public function getLastComment(int $torrentId): ?array
    {
        $lastcom = NexusDB::table('comments')->where('torrent', $torrentId)->orderBy('id', 'desc')->first();

        return $lastcom ? array_merge((array) $lastcom, array_values((array) $lastcom)) : null;
    }

    /**
     * Get torrent tag records keyed by torrent id.
     *
     * @param  array<int, int>  $torrentIds
     * @return  \Illuminate\Support\Collection<int|string, \Illuminate\Database\Eloquent\Collection<int, TorrentTag>>
     */
    public function getTorrentTagsGrouped(array $torrentIds)
    {
        return TorrentTag::query()->whereIn('torrent_id', $torrentIds)->get()->groupBy('torrent_id');
    }

    /**
     * Get seed-box peer info keyed by torrent id for the torrent list table.
     *
     * @param  array<int, int>  $torrentIds
     * @return  \Illuminate\Support\Collection<int|string, mixed>
     */
    public function getSeedBoxPeerInfo(array $torrentIds)
    {
        return Peer::query()
            ->whereIn('torrent', $torrentIds)
            ->where('seeder', 'yes')
            ->where('is_seed_box', '1')
            ->get()
            ->keyBy('torrent');
    }

    /**
     * Fetch a torrent as an array for the legacy "torrent to user" value calculation.
     *
     * @return  array<string, mixed>|null
     */
    public function findForUserValue(int $torrentId): ?array
    {
        return Torrent::query()->find($torrentId)?->toArray();
    }

    /**
     * Return the bookmarked torrent ids for a user.
     *
     * Mirrors the legacy {@see \App\Support\TorrentBookmark::bookmarkArray()}.
     *
     * @return  array<int, int>
     */
    public function getBookmarkTorrentIds(int $userId): array
    {
        $rows = Bookmark::query()->where('userid', $userId)->pluck('torrentid')->all();

        if (empty($rows)) {
            return [0];
        }

        return array_map(fn ($id) => (int) $id, $rows);
    }

    /**
     * Delete one or more torrents and related records.
     *
     * Mirrors the legacy {@see \App\Support\TorrentOps::deleteTorrents()}.
     *
     * @param  int|int[]  $id
     */
    public function deleteTorrents(int|array $id, bool $notify = false): void
    {
        $idArr = array_map('intval', is_array($id) ? $id : [$id]);

        $torrentInfo = Torrent::query()
            ->whereIn('id', $idArr)
            ->get()
            ->keyBy('id');

        $torrentDir = SiteConfig::current()->main->torrentDir();

        NexusDB::table('torrents')->whereIn('id', $idArr)->delete();
        NexusDB::table('torrent_extras')->whereIn('torrent_id', $idArr)->delete();
        NexusDB::table('snatched')
            ->whereIn('torrentid', $idArr)
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')->from('users')->whereColumn('users.id', '=', 'snatched.userid');
            })
            ->delete();

        foreach (['peers', 'files', 'comments'] as $x) {
            NexusDB::table($x)->whereIn('torrent', $idArr)->delete();
        }

        NexusDB::table('hit_and_runs')->whereIn('torrent_id', $idArr)->delete();

        foreach ($idArr as $_id) {
            /** @var Torrent|null $torrent */
            $torrent = $torrentInfo->get($_id);

            if ($torrent instanceof Torrent) {
                $this->delPiecesHashCache((string) $torrent->getAttribute('pieces_hash'));
            }

            Logger::writeWithContext("delete torrent: $_id", 'error');
            @unlink(Path::resolve("$torrentDir/$_id.torrent", defined('ROOT_PATH') ? (string) ROOT_PATH : ''));

            TorrentOperationLog::add([
                'torrent_id' => $_id,
                'uid' => UserDisplay::currentId(),
                'action_type' => TorrentOperationLog::ACTION_TYPE_DELETE,
                'comment' => '',
            ], $notify);

            Hooks::doAction('torrent_delete', $_id);
            if ($torrent instanceof Torrent) {
                Events::fire('torrent_deleted', $torrent);
            }
        }

        try {
            $meiliSearchRep = new MeiliSearchRepository();
            $meiliSearchRep->deleteDocuments($idArr);
        } catch (\Throwable $e) {
            Logger::writeWithContext('MeiliSearch delete on torrent delete failed: ' . $e->getMessage(), 'error');
        }
    }
    public function touchCacheStamp(int|string $torrentId, string $field = 'cache_stamp'): void
    {
        NexusDB::table('torrents')
            ->where('id', $torrentId)
            ->update([$field => time()]);
    }

    public function resetCacheStamp(int|string $torrentId, string $field = 'cache_stamp'): void
    {
        NexusDB::table('torrents')
            ->where('id', $torrentId)
            ->update([$field => 0]);
    }

    public static function getApprovalDenyCount(int $ownerId): int
    {
        return (int) Torrent::query()
            ->where('owner', $ownerId)
            ->where('approval_status', Torrent::APPROVAL_STATUS_DENY)
            ->count();
    }

    /**
     * @return array<string, mixed>|false
     */
    public static function getSnatchInfo(int|string $torrentId, int|string $userId): array|false
    {
        $record = NexusDB::table('snatched')
            ->where('torrentid', (int) $torrentId)
            ->where('userid', (int) $userId)
            ->orderBy('id', 'desc')
            ->first();

        return $record ? (array) $record : false;
    }

}
