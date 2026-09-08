<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\UserStatus;
use App\Models\UserModifyLog;
use App\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * W1-07: Asynchronous bulk staff message to selected user classes.
 *
 * Replaces the synchronous loop in StaffMessageController::takeStaffmess.
 * Runs in the queue so the web request returns immediately. Idempotent:
 * a unique idempotency key prevents duplicate execution.
 */
class BulkUserMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $backoff = 60;

    private const MAX_BATCH_SIZE = 10000;

    /**
     * @param  array<int, int>  $classIds  User class ids to target.
     * @param  int|null  $senderId  Sender user id, or null for "system".
     * @param  string  $subject  Message subject.
     * @param  string  $body  Message body.
     * @param  int|null  $actorId  The staff member who submitted the operation (for audit).
     * @param  string  $idempotencyKey  Unique key; duplicate submissions are skipped.
     * @param  bool  $dryRun  When true, only report what would change without writing.
     */
    public function __construct(
        private array $classIds,
        private ?int $senderId,
        private string $subject,
        private string $body,
        private ?int $actorId,
        private string $idempotencyKey,
        private bool $dryRun = false,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $logPrefix = sprintf(
            'BulkUserMessageJob key=%s classes=%s dryRun=%s',
            $this->idempotencyKey,
            implode(',', $this->classIds),
            $this->dryRun ? '1' : '0',
        );

        if ($this->alreadyProcessed()) {
            Logger::writeWithContext((string) "{$logPrefix}, already processed, skipping.", (string) 'info', (bool) false);

            return;
        }

        $beginTimestamp = microtime(true);
        $messagesSent = 0;
        $size = self::MAX_BATCH_SIZE;
        $page = 1;
        $added = now()->toDateTimeString();

        while (true) {
            $offset = ($page - 1) * $size;
            $rows = DB::table('users')
                ->whereIn('class', $this->classIds)
                ->where('enabled', true)
                ->where('status', UserStatus::CONFIRMED->value)
                ->offset($offset)
                ->limit($size)
                ->get(['id']);

            if ($rows->isEmpty()) {
                break;
            }

            if (! $this->dryRun) {
                $msgRecords = [];
                foreach ($rows as $dat) {
                    $msgRecords[] = [
                        'sender' => $this->senderId,
                        'receiver' => $dat->id,
                        'added' => $added,
                        'subject' => $this->subject,
                        'msg' => $this->body,
                    ];
                }
                DB::table('messages')->insert($msgRecords);
                $messagesSent += count($msgRecords);
            }

            $page++;
        }

        if (! $this->dryRun && $this->actorId !== null) {
            UserModifyLog::query()->insert([
                'user_id' => $this->actorId,
                'content' => sprintf(
                    'Bulk staff message to classes [%s], %d recipients (idempotency=%s).',
                    implode(',', $this->classIds),
                    $messagesSent,
                    $this->idempotencyKey,
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->markProcessed();

        Logger::writeWithContext(
            (string) sprintf(
                '%s, done. messages=%d cost=%.2fs',
                $logPrefix,
                $messagesSent,
                microtime(true) - $beginTimestamp,
            ),
            (string) 'info',
            (bool) false,
        );
    }

    public function failed(\Throwable $exception): void
    {
        Logger::writeWithContext(
            (string) sprintf(
                'BulkUserMessageJob key=%s failed: %s',
                $this->idempotencyKey,
                $exception->getMessage(),
            ),
            (string) 'error',
            (bool) false,
        );
    }

    private function alreadyProcessed(): bool
    {
        return cache()->has($this->processedCacheKey());
    }

    private function markProcessed(): void
    {
        cache()->put($this->processedCacheKey(), true, now()->addDay());
    }

    private function processedCacheKey(): string
    {
        return 'bulk_message:done:'.$this->idempotencyKey;
    }
}
