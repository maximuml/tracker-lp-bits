<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ModelEvent;
use Illuminate\Support\Facades\Redis;

/**
 * Legacy model-event helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `publish_model_event()`.
 */
final class Events
{
    /**
     * Publish a lightweight model-change event to Redis.
     *
     * Mirrors `publish_model_event()`.
     */
    public static function publishModel(ModelEvent $event, int $id, string $json = ''): void
    {
        $name = $event->value;
        $channel = Env::get('CHANNEL_NAME_MODEL_EVENT', null);
        if (! empty($channel)) {
            RedisGuard::attempt(static fn () => Redis::connection()->client()->publish($channel, json_encode(['event' => $name, 'id' => $id, 'json' => $json])));
        } else {
            Logger::writeWithContext("event: $name, id: $id, channel: ".(is_scalar($channel) ? (string) $channel : '').', channel is empty!', 'error');
        }
    }
}
