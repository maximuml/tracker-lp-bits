<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TorrentBuyLog;
use App\Repositories\BonusRepository;
use App\Repositories\TorrentRepository;
use App\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BuyTorrent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public int $timeout = 300;

    public int $userId;

    public int $torrentId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $userId, int $torrentId)
    {
        $this->userId = $userId;
        $this->torrentId = $torrentId;
    }

    /**
     * Execute the job.
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function handle()
    {
        $logPrefix = sprintf('user: %s, torrent: %s', $this->userId, $this->torrentId);
        $torrentRep = app(TorrentRepository::class);
        $userId = $this->userId;
        $torrentId = $this->torrentId;

        $buyLog = TorrentBuyLog::query()
            ->where('uid', $userId)
            ->where('torrent_id', $torrentId)
            ->first();

        if ($buyLog) {
            // 标记购买成功
            Logger::writeWithContext((string) "{$logPrefix}, already bought", (string) 'info', (bool) false);
            $torrentRep->addBuySuccessCache($userId, $torrentId, $buyLog->id);

            return;
        }
        try {
            $bonusRep = app(BonusRepository::class);
            $buyLog = $bonusRep->consumeToBuyTorrent($this->userId, $this->torrentId);
            // 标记购买成功
            Logger::writeWithContext((string) "{$logPrefix}, buy torrent success", (string) 'info', (bool) false);
            $torrentRep->addBuySuccessCache($userId, $torrentId, $buyLog->id);
        } catch (\Throwable $throwable) {
            // 标记购买失败，缓存 3600 秒，这个时间内不能再次购买
            Logger::writeWithContext((string) ("{$logPrefix}, buy torrent fail: ".$throwable->getMessage()), (string) 'error', (bool) false);
            $torrentRep->addBuyFailCache($userId, $torrentId);
            throw $throwable;
        }
    }
}
