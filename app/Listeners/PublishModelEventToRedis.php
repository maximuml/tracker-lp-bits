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
use App\Support\Env;
use App\Support\Logger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis;

/**
 * W2-10: Publish model-change events to Redis pub/sub.
 *
 * Replaces the manual Events::publishModel() call that was embedded
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
        TorrentCreated::class => 'torrent_created',
        TorrentUpdated::class => 'torrent_updated',
        TorrentDeleted::class => 'torrent_deleted',
        UserCreated::class => 'user_created',
        UserUpdated::class => 'user_updated',
        UserDeleted::class => 'user_deleted',
        UserEnabled::class => 'user_enabled',
        UserDisabled::class => 'user_disabled',
        NewsCreated::class => 'news_created',
        HitAndRunCreated::class => 'hit_and_run_created',
        HitAndRunUpdated::class => 'hit_and_run_updated',
        HitAndRunDeleted::class => 'hit_and_run_deleted',
        MessageCreated::class => 'message_created',
        StaffMessageCreated::class => 'staff_message_created',
        SnatchedUpdated::class => 'snatched_updated',
        AgentAllowCreated::class => 'agent_allow_created',
        AgentAllowUpdated::class => 'agent_allow_updated',
        AgentAllowDeleted::class => 'agent_allow_deleted',
        AgentDenyCreated::class => 'agent_deny_created',
        AgentDenyUpdated::class => 'agent_deny_updated',
        AgentDenyDeleted::class => 'agent_deny_deleted',
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
        } else {
            return;
        }

        $channel = Env::get('CHANNEL_NAME_MODEL_EVENT', null);

        if (! empty($channel)) {
            Redis::connection()->client()->publish(
                $channel,
                json_encode(['event' => $name, 'id' => $id, 'json' => $json], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            );
        } else {
            Logger::writeWithContext("event: $name, id: $id, channel: ".(is_scalar($channel) ? (string) $channel : '').', channel is empty!', 'error');
        }
    }
}
