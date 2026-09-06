<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AgentAllowCreated;
use App\Events\AgentAllowDeleted;
use App\Events\AgentAllowUpdated;
use App\Events\AgentDenyCreated;
use App\Events\AgentDenyDeleted;
use App\Events\AgentDenyUpdated;
use App\Events\HitAndRunCreated;
use App\Events\HitAndRunDeleted;
use App\Events\HitAndRunUpdated;
use App\Events\MessageCreated;
use App\Events\NewsCreated;
use App\Events\SnatchedUpdated;
use App\Events\StaffMessageCreated;
use App\Events\TorrentCreated;
use App\Events\TorrentDeleted;
use App\Events\TorrentUpdated;
use App\Events\UserCreated;
use App\Events\UserDeleted;
use App\Events\UserDisabled;
use App\Events\UserEnabled;
use App\Events\UserUpdated;
use App\Services\OutboxService;
use Illuminate\Database\Eloquent\Model;

/**
 * W2-10: Records model events to the transactional outbox.
 *
 * This listener is registered for all model event classes and
 * automatically records them to the outbox table, replacing the
 * manual outbox calls that were scattered across services.
 *
 * The listener is synchronous (runs in the same transaction as
 * the event dispatch), ensuring the outbox row is committed
 * atomically with the domain change.
 */
final class RecordModelEventToOutbox
{
    /**
     * Maps event class → (aggregateType, eventTypePrefix).
     *
     * @var array<class-string, array{string, string}>
     */
    private const EVENT_MAP = [
        TorrentCreated::class => ['torrent', 'torrent.created'],
        TorrentUpdated::class => ['torrent', 'torrent.updated'],
        TorrentDeleted::class => ['torrent', 'torrent.deleted'],
        UserCreated::class => ['user', 'user.created'],
        UserUpdated::class => ['user', 'user.updated'],
        UserDeleted::class => ['user', 'user.deleted'],
        UserEnabled::class => ['user', 'user.enabled'],
        UserDisabled::class => ['user', 'user.disabled'],
        NewsCreated::class => ['news', 'news.created'],
        HitAndRunCreated::class => ['hit_and_run', 'hit_and_run.created'],
        HitAndRunUpdated::class => ['hit_and_run', 'hit_and_run.updated'],
        HitAndRunDeleted::class => ['hit_and_run', 'hit_and_run.deleted'],
        MessageCreated::class => ['message', 'message.created'],
        StaffMessageCreated::class => ['staff_message', 'staff_message.created'],
        SnatchedUpdated::class => ['snatch', 'snatch.updated'],
        AgentAllowCreated::class => ['agent_allow', 'agent_allow.created'],
        AgentAllowUpdated::class => ['agent_allow', 'agent_allow.updated'],
        AgentAllowDeleted::class => ['agent_allow', 'agent_allow.deleted'],
        AgentDenyCreated::class => ['agent_deny', 'agent_deny.created'],
        AgentDenyUpdated::class => ['agent_deny', 'agent_deny.updated'],
        AgentDenyDeleted::class => ['agent_deny', 'agent_deny.deleted'],
    ];

    public function __construct(
        private readonly OutboxService $outboxService = new OutboxService,
    ) {}

    public function handle(object $event): void
    {
        $eventClass = $event::class;

        if (! isset(self::EVENT_MAP[$eventClass])) {
            return;
        }

        [$aggregateType, $eventType] = self::EVENT_MAP[$eventClass];

        // Extract model data and ID from the event
        if (isset($event->model) && $event->model instanceof Model) {
            $this->outboxService->record(
                aggregateType: $aggregateType,
                eventType: $eventType,
                payload: $event->model->toArray(),
                aggregateId: (int) $event->model->getKey(),
            );
        } elseif (isset($event->data) && is_array($event->data)) {
            $id = (int) ($event->data['id'] ?? 0);
            $this->outboxService->record(
                aggregateType: $aggregateType,
                eventType: $eventType,
                payload: $event->data,
                aggregateId: $id > 0 ? $id : null,
            );
        }
    }
}
