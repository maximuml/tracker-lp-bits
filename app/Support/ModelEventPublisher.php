<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ModelEvent;
use Illuminate\Support\Facades\Redis;

/**
 * Publishes lightweight model-change pings on the
 * CHANNEL_NAME_MODEL_EVENT Redis channel for external subscribers
 * (peer trackers, dashboards).
 *
 * Replaces `publish_model_event()`.
 */
final class ModelEventPublisher
{
    /**
     * Publish a lightweight model-change event to Redis.
     */
    public static function publish(ModelEvent $event, int $id, string $json = ''): void
    {
        self::send($event->value, $id, $json);
    }

    /**
     * Low-level send by wire name — used by the Laravel-event listener
     * that maps event classes to names itself.
     */
    public static function send(string $name, int $id, string $json = '', int $jsonFlags = 0): void
    {
        $channel = Env::get('CHANNEL_NAME_MODEL_EVENT', null);
        if (! empty($channel)) {
            RedisGuard::attempt(static fn () => Redis::connection()->client()->publish($channel, json_encode(['event' => $name, 'id' => $id, 'json' => $json], $jsonFlags)));
        } else {
            Logger::writeWithContext("event: $name, id: $id, channel: ".(is_scalar($channel) ? (string) $channel : '').', channel is empty!', 'error');
        }
    }
}
