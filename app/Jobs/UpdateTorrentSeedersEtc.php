<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Support\Cache as AppCache;
use App\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdateTorrentSeedersEtc implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $beginTorrentId;

    private int $endTorrentId;

    private string $requestId;

    private ?string $idStr = null;

    private string $idRedisKey;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $beginTorrentId, int $endTorrentId, string $idStr, string $idRedisKey, string $requestId = '')
    {
        $this->beginTorrentId = $beginTorrentId;
        $this->endTorrentId = $endTorrentId;
        $this->idStr = $idStr;
        $this->idRedisKey = $idRedisKey;
        $this->requestId = $requestId;
        $this->onQueue('tracker-critical');
    }

    /** @var int */
    public $tries = 1;

    /** @var int */
    public $timeout = 1800;

    public int $backoff = 60;

    /**
     * 获取任务时，应该通过的中间件。
     *
     * @return array<int, WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [new WithoutOverlapping($this->idRedisKey)];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $beginTimestamp = time();
        $logPrefix = sprintf(
            '[CLEANUP_CLI_UPDATE_TORRENT_SEEDERS_ETC_HANDLE_JOB], commonRequestId: %s, beginTorrentId: %s, endTorrentId: %s, idStr: %s, idRedisKey: %s',
            $this->requestId, $this->beginTorrentId, $this->endTorrentId, $this->idStr, $this->idRedisKey
        );
        Logger::writeWithContext((string) "{$logPrefix}, job start ...", (string) 'info', (bool) false);

        $idStr = $this->idStr;
        $delIdRedisKey = false;
        if (empty($idStr) && ! empty($this->idRedisKey)) {
            $delIdRedisKey = true;
            $idStr = Cache::get($this->idRedisKey);
        }
        if (empty($idStr)) {
            Logger::writeWithContext((string) "{$logPrefix}, no idStr or idRedisKey", (string) 'error', (bool) false);

            return;
        }
        $torrentIdArr = array_filter(array_map('intval', explode(',', $idStr)));
        if (empty($torrentIdArr)) {
            Logger::writeWithContext((string) "{$logPrefix}, empty idStr", (string) 'error', (bool) false);

            return;
        }
        // 批量取，简单化
        $torrents = [];
        $res = DB::table('peers')
            ->selectRaw('torrent, seeder, COUNT(*) AS c')
            ->whereIn('torrent', $torrentIdArr)
            ->groupBy(['torrent', 'seeder'])
            ->get();
        if ($res->isEmpty()) {
            Logger::writeWithContext((string) "{$logPrefix}, no data from idStr: {$idStr}", (string) 'error', (bool) false);

            return;
        }
        foreach ($res as $row) {
            if ($row->seeder == 1) {
                $key = 'seeders';
            } else {
                $key = 'leechers';
            }
            $torrents[$row->torrent][$key] = $row->c;
        }

        $res = DB::table('comments')
            ->selectRaw('torrent, COUNT(*) AS c')
            ->whereIn('torrent', $torrentIdArr)
            ->groupBy(['torrent'])
            ->get();
        foreach ($res as $row) {
            $torrents[$row->torrent]['comments'] = $row->c;
        }
        $rows = [];
        foreach ($torrentIdArr as $id) {
            $rows[] = [
                'id' => $id,
                'seeders' => $torrents[$id]['seeders'] ?? 0,
                'leechers' => $torrents[$id]['leechers'] ?? 0,
                'comments' => $torrents[$id]['comments'] ?? 0,
            ];
        }
        $result = DB::table('torrents')->upsert($rows, ['id'], ['seeders', 'leechers', 'comments']);
        if ($delIdRedisKey) {
            AppCache::forgetWithLocales($this->idRedisKey);
        }
        $costTime = time() - $beginTimestamp;
        Logger::writeWithContext((string) sprintf("{$logPrefix}, [DONE], update torrent count: %s, result: %s, cost time: %s seconds", count($torrentIdArr), var_export($result, true), $costTime), (string) 'info', (bool) false);
        Logger::writeWithContext((string) "{$logPrefix}, upsert torrents seeders/leechers/comments done", (string) 'debug', (bool) false);
    }

    /**
     * Handle a job failure.
     *
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Logger::writeWithContext((string) ('failed: '.$exception->getMessage().$exception->getTraceAsString()), (string) 'error', (bool) false);
    }
}
