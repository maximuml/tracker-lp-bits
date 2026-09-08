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
 * W1-07: Asynchronous bulk increment of user fields by class.
 *
 * Replaces the synchronous loops in SystemBulkController::takeamountupload
 * and SystemBulkController::takeIncrementBulk. Runs in the queue so the
 * web request returns immediately. Idempotent: a unique idempotency key
 * prevents duplicate execution for the same submission.
 */
class BulkUserIncrementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $backoff = 60;

    private const MAX_BATCH_SIZE = 2000;

    /**
     * @param  array<int, int>  $classIds  User class ids to target.
     * @param  string  $field  Column to increment (uploaded, seedbonus, invites, tmp_invites, ...).
     * @param  int|float  $amount  Increment amount (already unit-converted for uploaded).
     * @param  int|null  $actorId  The user who submitted the operation (for audit).
     * @param  string  $idempotencyKey  Unique key; duplicate submissions are skipped.
     * @param  array{sender: int|null, subject: string, msg: string}  $message  Optional message to send each affected user.
     * @param  bool  $dryRun  When true, only report what would change without writing.
     */
    public function __construct(
        private array $classIds,
        private string $field,
        private int|float $amount,
        private ?int $actorId,
        private string $idempotencyKey,
        private array $message,
        private bool $dryRun = false,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $logPrefix = sprintf(
            'BulkUserIncrementJob key=%s field=%s amount=%d classes=%s dryRun=%s',
            $this->idempotencyKey,
            $this->field,
            $this->amount,
            implode(',', $this->classIds),
            $this->dryRun ? '1' : '0',
        );

        // Idempotency guard: skip if already processed.
        if ($this->alreadyProcessed()) {
            Logger::writeWithContext((string) "{$logPrefix}, already processed, skipping.", (string) 'info', (bool) false);

            return;
        }

        $beginTimestamp = microtime(true);
        $affectedUsers = 0;
        $messagesSent = 0;
        $size = self::MAX_BATCH_SIZE;
        $page = 1;
        $added = now()->toDateTimeString();

        while (true) {
            $offset = ($page - 1) * $size;
            $users = DB::table('users')
                ->whereIn('class', $this->classIds)
                ->where('enabled', true)
                ->where('status', UserStatus::CONFIRMED->value)
                ->offset($offset)
                ->limit($size)
                ->get(['id']);

            if ($users->isEmpty()) {
                break;
            }

            $idArr = [];
            foreach ($users as $userRow) {
                $idArr[] = (int) $userRow->id;
            }

            if (! $this->dryRun) {
                DB::table('users')->whereIn('id', $idArr)->increment($this->field, (int) $this->amount);

                if ($this->message['msg'] !== '') {
                    $msgRows = [];
                    foreach ($idArr as $id) {
                        $msgRows[] = [
                            'sender' => $this->message['sender'],
                            'receiver' => $id,
                            'added' => $added,
                            'subject' => $this->message['subject'],
                            'msg' => $this->message['msg'],
                        ];
                    }
                    DB::table('messages')->insert($msgRows);
                    $messagesSent += count($msgRows);
                }
            }

            $affectedUsers += count($idArr);
            $page++;
        }

        // Audit trail.
        if (! $this->dryRun && $this->actorId !== null) {
            UserModifyLog::query()->insert([
                'user_id' => $this->actorId,
                'content' => sprintf(
                    'Bulk increment %s by %d for classes [%s] affecting %d users (idempotency=%s).',
                    $this->field,
                    $this->amount,
                    implode(',', $this->classIds),
                    $affectedUsers,
                    $this->idempotencyKey,
                ),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->markProcessed();

        Logger::writeWithContext(
            (string) sprintf(
                '%s, done. affected=%d messages=%d cost=%.2fs',
                $logPrefix,
                $affectedUsers,
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
                'BulkUserIncrementJob key=%s failed: %s',
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
        // Keep the marker for 24h to absorb retries/duplicate dispatches.
        cache()->put($this->processedCacheKey(), true, now()->addDay());
    }

    private function processedCacheKey(): string
    {
        return 'bulk_increment:done:'.$this->idempotencyKey;
    }
}
