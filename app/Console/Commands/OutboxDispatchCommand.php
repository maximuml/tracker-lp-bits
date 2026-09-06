<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\OutboxDispatcher;
use Illuminate\Console\Command;

/**
 * T-24: Dispatch pending outbox events.
 *
 * Can be run via scheduler or as a queue worker. Claims pending events,
 * publishes them to Redis pub/sub, and handles retry/backoff/dead-letter.
 */
final class OutboxDispatchCommand extends Command
{
    protected $signature = 'outbox:dispatch {--batch=50 : Maximum events to dispatch per run}';

    protected $description = 'Dispatch pending outbox events to pub/sub channels';

    public function handle(OutboxDispatcher $dispatcher): int
    {
        $batchSize = (int) $this->option('batch');

        $this->info("Dispatching up to {$batchSize} outbox events...");

        $dispatched = $dispatcher->dispatch($batchSize);

        $this->info("Dispatched {$dispatched} events.");

        $pending = $dispatcher->pendingCount();
        $deadLetter = $dispatcher->deadLetterCount();

        if ($pending > 0) {
            $this->warn("Remaining pending: {$pending}");
        }
        if ($deadLetter > 0) {
            $this->error("Dead-letter queue: {$deadLetter} events");
        }

        return self::SUCCESS;
    }
}
