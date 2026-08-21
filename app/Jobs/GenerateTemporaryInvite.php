<?php

namespace App\Jobs;

use App\Models\Invite;
use App\Repositories\ToolRepository;
use App\Support\Logger;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nexus\Database\NexusDB;

class GenerateTemporaryInvite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private int $count;

    private string $idRedisKey;

    private int $days;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(string $idRedisKey, int $days, int $count)
    {
        $this->idRedisKey = $idRedisKey;
        $this->days = $days;
        $this->count = $count;
    }

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $beginTimestamp = microtime(true);
        $toolRep = new ToolRepository;
        $idStr = NexusDB::cache_get($this->idRedisKey);
        $logPrefix = 'idRedisKey: '.$this->idRedisKey;
        if (empty($idStr)) {
            Logger::writeWithContext((string) "{$logPrefix}, no idStr...", (string) 'info', (bool) false);

            return;
        }
        $idArr = explode(',', $idStr);
        $count = count($idArr);
        $logPrefix .= ", count: $count";
        Logger::writeWithContext((string) "{$logPrefix}, going to handle...", (string) 'info', (bool) false);
        $now = Carbon::now();
        $expiredAt = Carbon::now()->addDays($this->days);
        foreach ($idArr as $uid) {
            try {
                $hashArr = $toolRep->generateUniqueInviteHash([], $this->count, $this->count);
                $data = [];
                foreach ($hashArr as $hash) {
                    $data[] = [
                        'inviter' => $uid,
                        'invitee' => '',
                        'hash' => $hash,
                        'valid' => 0,
                        'expired_at' => $expiredAt,
                        'created_at' => $now,
                    ];
                }
                if (! empty($data)) {
                    Invite::query()->insert($data);
                }
                Logger::writeWithContext((string) "{$logPrefix}, success add {$this->count} temporary invite ({$this->days} days) to {$uid}", (string) 'info', (bool) false);
            } catch (\Exception $exception) {
                Logger::writeWithContext((string) ("{$logPrefix}, fail add {$this->count} temporary invite ({$this->days} days) to {$uid}: ".$exception->getMessage()), (string) 'error', (bool) false);
            }
        }
        NexusDB::cache_del($this->idRedisKey);
        Logger::writeWithContext((string) ("{$logPrefix}, handle done, cost time: ".(microtime(true) - $beginTimestamp).' seconds.'), (string) 'info', (bool) false);
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
