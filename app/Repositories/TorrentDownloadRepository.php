<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Torrent;
use App\Models\TorrentSecret;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Env;
use App\Support\Logger;
use App\Support\Url;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Hashids\Hashids;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Rhilip\Bencode\Bencode;

/**
 * Handles download URL/hashing, tracker report auth keys, and pieces-hash cache.
 *
 * Extracted from TorrentRepository to reduce god-object surface area.
 */
class TorrentDownloadRepository extends BaseRepository
{
    public const PIECES_HASH_CACHE_KEY = 'torrent_pieces_hash';

    /** @var array<string, string> */
    private static array $downHashKeys = [];

    /**
     * @param  mixed  $id
     * @param  array<int|string, mixed>|User  $user
     */
    public function getDownloadUrl($id, array|User $user): string
    {
        return sprintf(
            '%s/download.php?downhash=%s.%s',
            Url::schemeAndHost(false), is_array($user) ? $user['id'] : $user->id, $this->encryptDownHash($id, $user)
        );
    }

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
     * @return array<int|string, mixed>
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

        Logger::write("Invalid down hash: $downHash", 'error');

        return [];
    }

    /**
     * @param  mixed  $user
     * @return array{id: int, passkey: string}
     */
    private function getUserForDownHash($user): array
    {
        $passkey = '';
        if ($user instanceof User && $user->passkey) {
            $passkey = $user->passkey;
            $id = (int) $user->id;
        } elseif (is_array($user) && ! empty($user['passkey'])) {
            $passkey = $user['passkey'];
            $id = (int) $user['id'];
        } elseif (is_scalar($user)) {
            $user = User::query()->findOrFail(intval($user), ['id', 'passkey']);
            $passkey = $user->passkey;
            $id = (int) $user->id;
        } else {
            throw new \InvalidArgumentException('Invalid user: '.json_encode($user));
        }

        if (empty($passkey)) {
            throw new \InvalidArgumentException('Invalid user: '.json_encode($user));
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
        $cacheKey = $userId.':'.$passkey;
        if (isset(self::$downHashKeys[$cacheKey])) {
            return self::$downHashKeys[$cacheKey];
        }

        $appKey = (string) Env::get('APP_KEY', '');
        if ($appKey === '') {
            throw new \RuntimeException('APP_KEY is not configured for downhash');
        }

        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        return self::$downHashKeys[$cacheKey] = hash_hkdf('sha256', $appKey, 32, 'nexus-downhash-'.$userId.'-'.$passkey);
    }

    private function getLegacyMd5DownHashKey(int $userId, string $passkey, string $dateYmd): string
    {
        return md5($passkey.$dateYmd.$userId);
    }

    /**
     * @param  mixed  $id
     * @param  mixed  $uid
     * @param  mixed  $initializeIfNotExists
     *
     * @deprecated
     *
     * @throws NexusException
     */
    public function getTrackerReportAuthKey($id, $uid, $initializeIfNotExists = false): string
    {
        $key = $this->getTrackerReportAuthKeySecret($id, $uid, $initializeIfNotExists);
        $hash = (new Hashids($key))->encode(date('Ymd'));

        return sprintf('%s|%s|%s', $id, $uid, $hash);
    }

    /**
     * @param  mixed  $authKey
     * @return array<int|string, mixed>
     *
     * @deprecated
     *
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
     * @return mixed
     */
    private function getTrackerReportAuthKeySecret($id, $uid, $initializeIfNotExists = false)
    {
        $secret = Cache::remember("torrent_secret_{$uid}_{$id}", 3600, function () use ($id, $uid) {
            return TorrentSecret::query()
                ->where('uid', $uid)
                ->whereIn('torrent_id', [0, $id])
                ->orderBy('torrent_id', 'desc')
                ->orderBy('id', 'desc')
                ->first() ?? false;
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
            Logger::writeWithContext((string) ('[INSERT_TORRENT_SECRET] '.json_encode($insert)), (string) 'info', (bool) false);
            TorrentSecret::query()->insert($insert);

            return $insert['secret'];
        }
        throw new NexusException('No valid report secret, please re-download this torrent.');
    }

    /**
     * @param  mixed  $uid
     * @param  mixed  $torrentId
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

    public function addPiecesHashCache(int $torrentId, string $piecesHash): bool|int|\Redis
    {
        $value = $this->buildPiecesHashCacheValue($torrentId, $piecesHash);

        return Redis::connection()->client()->hSet(self::PIECES_HASH_CACHE_KEY, $piecesHash, $value);
    }

    private function buildPiecesHashCacheValue(int $torrentId, string $piecesHash): bool|string
    {
        return json_encode(['torrent_id' => $torrentId, 'pieces_hash' => $piecesHash]);
    }

    public function delPiecesHashCache(string $piecesHash): bool|int|\Redis
    {
        return Redis::connection()->client()->hDel(self::PIECES_HASH_CACHE_KEY, $piecesHash);
    }

    /**
     * @param  mixed  $piecesHash
     * @return array<int|string, mixed>
     */
    public function getPiecesHashCache($piecesHash): array
    {
        if (! is_array($piecesHash)) {
            $piecesHash = [$piecesHash];
        }
        $maxCount = 100;
        if (count($piecesHash) > $maxCount) {
            throw new \InvalidArgumentException("too many pieces hash, must less then $maxCount");
        }
        $pipe = Redis::connection()->client()->multi(\Redis::PIPELINE);
        foreach ($piecesHash as $hash) {
            $pipe->hGet(self::PIECES_HASH_CACHE_KEY, $hash);
        }
        $results = $pipe->exec();
        $logPrefix = sprintf('piecesHashCount: %s, resultCount: %s', count($piecesHash), count($results));
        $out = [];
        foreach ($results as $item) {
            $arr = json_decode($item, true);
            if (is_array($arr) && isset($arr['torrent_id'], $arr['pieces_hash'])) {
                $out[$arr['pieces_hash']] = $arr['torrent_id'];
            } else {
                Logger::writeWithContext((string) sprintf('%s, invalid item: %s(%s)', $logPrefix, var_export($item, true), gettype($item)), (string) 'info', (bool) false);
            }
        }

        return $out;
    }

    /**
     * @param  mixed  $id
     * @return array<int|string, mixed>
     */
    public function loadPiecesHashCache($id = 0): array
    {
        $page = 1;
        $size = 1000;
        $query = Torrent::query();
        if ($id) {
            $query = $query->whereIn('id', Arr::wrap($id));
        }
        $total = $success = 0;
        $torrentDir = sprintf(
            '%s/%s/',
            rtrim(ROOT_PATH, '/'),
            rtrim(SiteConfig::current()->main->torrentDir(), '/')
        );
        while (true) {
            $list = (clone $query)->forPage($page, $size)->get(['id', 'pieces_hash']);
            if ($list->isEmpty()) {
                Logger::writeWithContext((string) "page: {$page}, size: {$size}, no more data...", (string) 'info', (bool) false);
                break;
            }
            $pipe = Redis::connection()->client()->multi(\Redis::PIPELINE);
            $piecesHashRows = [];
            $currentCount = 0;
            foreach ($list as $item) {
                $total++;
                try {
                    $piecesHash = $item->pieces_hash;
                    if (! $piecesHash) {
                        $torrentFile = $torrentDir.$item->id.'.torrent';
                        $loadResult = Bencode::load($torrentFile);
                        $piecesHash = sha1($loadResult['info']['pieces']);
                        $piecesHashRows[] = [
                            'id' => (int) $item->id,
                            'pieces_hash' => $piecesHash,
                        ];
                        Logger::writeWithContext((string) sprintf('torrent: %s no pieces hash, load from torrent file: %s, pieces hash: %s', $item->id, $torrentFile, $piecesHash), (string) 'info', (bool) false);
                    }
                    $pipe->hSet(self::PIECES_HASH_CACHE_KEY, $piecesHash, $this->buildPiecesHashCacheValue($item->id, $piecesHash));
                    $success++;
                    $currentCount++;
                } catch (\Exception $exception) {
                    Logger::writeWithContext((string) sprintf('load pieces hash of torrent: %s error: %s', $item->id, $exception->getMessage()), (string) 'error', (bool) false);
                }
            }
            $pipe->exec();
            if (! empty($piecesHashRows)) {
                DB::table('torrents')->upsert($piecesHashRows, ['id'], ['pieces_hash']);
            }
            Logger::writeWithContext((string) "success load page: {$page}, size: {$size}, count: {$currentCount}", (string) 'info', (bool) false);
            $page++;
        }
        Logger::writeWithContext((string) "[DONE], total: {$total}, success: {$success}", (string) 'info', (bool) false);

        return compact('total', 'success');
    }

    public function touchCacheStamp(int|string $torrentId, string $field = 'cache_stamp'): void
    {
        DB::table('torrents')
            ->where('id', $torrentId)
            ->update([$field => time()]);
    }

    public function resetCacheStamp(int|string $torrentId, string $field = 'cache_stamp'): void
    {
        DB::table('torrents')
            ->where('id', $torrentId)
            ->update([$field => 0]);
    }
}
