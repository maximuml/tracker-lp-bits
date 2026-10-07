<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ModelEvent;
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
use App\Support\ModelEventPublisher;
use Illuminate\Database\Eloquent\Model;

/**
 * W2-10: Publish model-change events to Redis pub/sub.
 *
 * Replaces the manual ModelEventPublisher::publish() call that was embedded
 * in Events::fire(). Now that events are dispatched via Laravel's
 * event() helper, this listener handles the Redis publish side-effect.
 */
final class PublishModelEventToRedis
{
    /**
     * Maps event class → NexusPHP event name (for the Redis payload).
     *
     * @var array<class-string, string>
     */
    private const EVENT_NAMES = [
        TorrentCreated::class => ModelEvent::TorrentCreated->value,
        TorrentUpdated::class => ModelEvent::TorrentUpdated->value,
        TorrentDeleted::class => ModelEvent::TorrentDeleted->value,
        UserCreated::class => ModelEvent::UserCreated->value,
        UserUpdated::class => ModelEvent::UserUpdated->value,
        UserDeleted::class => ModelEvent::UserDeleted->value,
        UserEnabled::class => ModelEvent::UserEnabled->value,
        UserDisabled::class => ModelEvent::UserDisabled->value,
        NewsCreated::class => ModelEvent::NewsCreated->value,
        HitAndRunCreated::class => ModelEvent::HitAndRunCreated->value,
        HitAndRunUpdated::class => ModelEvent::HitAndRunUpdated->value,
        HitAndRunDeleted::class => ModelEvent::HitAndRunDeleted->value,
        MessageCreated::class => ModelEvent::MessageCreated->value,
        StaffMessageCreated::class => ModelEvent::StaffMessageCreated->value,
        SnatchedUpdated::class => ModelEvent::SnatchedUpdated->value,
        AgentAllowCreated::class => ModelEvent::AgentAllowCreated->value,
        AgentAllowUpdated::class => ModelEvent::AgentAllowUpdated->value,
        AgentAllowDeleted::class => ModelEvent::AgentAllowDeleted->value,
        AgentDenyCreated::class => ModelEvent::AgentDenyCreated->value,
        AgentDenyUpdated::class => ModelEvent::AgentDenyUpdated->value,
        AgentDenyDeleted::class => ModelEvent::AgentDenyDeleted->value,
    ];

    public function handle(object $event): void
    {
        $eventClass = $event::class;

        if (! isset(self::EVENT_NAMES[$eventClass])) {
            return;
        }

        $name = self::EVENT_NAMES[$eventClass];

        // Extract model and ID from the event
        if (isset($event->model) && $event->model instanceof Model) {
            $id = (int) $event->model->getKey();
            $json = $event->model->toJson();
        } elseif (isset($event->data) && is_array($event->data)) {
            $id = (int) ($event->data['id'] ?? 0);
            $json = json_encode($event->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return;
            }
        } else {
            return;
        }

        // Best-effort fan-out: a dead Redis costs ~5-10s of connect
        // stalls per event — the breaker keeps callers fast instead.
        ModelEventPublisher::send($name, $id, $json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
