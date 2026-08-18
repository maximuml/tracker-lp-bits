<?php

namespace App\Utils;

use App\Jobs\BuyTorrent;
use Illuminate\Support\Facades\Queue;
use Nexus\Database\NexusDB;
use Nexus\Database\NexusLock;

final class ThirdPartyJob {
    private static string $queueKey = "nexus_third_party_job";

    private static int $size = 20;

    const JOB_BUY_TORRENT = "buyTorrent";

    public function __invoke(): void
    {
        $lockName = \App\Support\Strings::namespaceToSnake(__METHOD__);
        $lock = new NexusLock($lockName, 600);
        if (!$lock->get()) {
            \App\Support\Logger::writeWithContext((string) "can not get lock: {$lockName}, return ...", (string) 'info', (bool) false);
            return;
        }
        $list = NexusDB::redis()->lRange(self::$queueKey, 0, self::$size);
        $successCount = 0;
        foreach ($list as $item) {
            $data = json_decode($item, true);
            if (!empty($data['name'])) {
                $successCount++;
                match ($data['name']) {
                    self::JOB_BUY_TORRENT => self::enqueueJobBuyTorrent($data),
                    default => throw new \InvalidArgumentException("invalid name: {$data['name']}")
                };
            } else {
                \App\Support\Logger::writeWithContext((string) sprintf("%s no name, skip", $item), (string) "error", (bool) false);
            }
            NexusDB::redis()->lRem(self::$queueKey, $item);
        }
        \App\Support\Logger::writeWithContext((string) sprintf("success dispatch %s jobs", $successCount), (string) 'info', (bool) false);
        $lock->release();
    }

    public static function addBuyTorrent(int $userId, int $torrentId): void
    {
        $key = sprintf("%s:%s_%s", self::$queueKey, $userId, $torrentId);
        if (NexusDB::redis()->set($key, now()->toDateTimeString(), ['nx', 'ex' => 3600])) {
            $value = [
                'name' => self::JOB_BUY_TORRENT,
                'user_id' => $userId,
                'torrent_id' => $torrentId,
            ];
            NexusDB::redis()->rPush(self::$queueKey, json_encode($value));
            \App\Support\Logger::writeWithContext((string) "success addBuyTorrent: {$key}", (string) "debug", (bool) false);
        } else {
            \App\Support\Logger::writeWithContext((string) "no need to addBuyTorrent: {$key}", (string) "debug", (bool) false);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function enqueueJobBuyTorrent(array $params): void
    {
        if (!empty($params['user_id']) && !empty($params['torrent_id'])) {
            $job = new BuyTorrent($params['user_id'], $params['torrent_id']);
            Queue::push($job);
        } else {
            \App\Support\Logger::writeWithContext((string) ("no user_id or torrent_id: " . json_encode($params)), (string) "error", (bool) false);
        }
    }
}
