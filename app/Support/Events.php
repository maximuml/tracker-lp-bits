<?php

namespace App\Support;

use App\Enums\ModelEventEnum;
use App\Jobs\FireEvent;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;
use Nexus\Nexus;

/**
 * Legacy model-event helpers extracted from `include/globalfunctions.php`.
 *
 * Backs `fire_event()` and `publish_model_event()`. The Laravel event
 * dispatch and Redis publish paths are preserved exactly.
 */
final class Events
{
    /**
     * Fire a model event either through the Laravel dispatcher or via the
     * Nexus queue worker, depending on the runtime context.
     *
     * Mirrors `fire_event()`.
     */
    public static function fire(string $name, Model $model, ?Model $oldModel = null): void
    {
        if (! isset(ModelEventEnum::$eventMaps[$name])) {
            throw new \InvalidArgumentException("Event $name is not a valid event enumeration");
        }

        if (defined('IN_NEXUS') && IN_NEXUS) {
            $prefix = 'fire_event:';
            $idKey = $prefix.Str::random();
            $idKeyOld = '';
            NexusDB::cache_put($idKey, json_encode($model->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 3600 * 24 * 30);
            if ($oldModel) {
                $idKeyOld = $prefix.Str::random();
                NexusDB::cache_put($idKeyOld, json_encode($oldModel->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 3600 * 24 * 30);
            }
            Nexus::dispatchQueueJob(new FireEvent($name, $idKey, $idKeyOld));
            Logger::writeWithContext("success fire_event in nexus, name: $name, idKey: $idKey, idKeyOld: $idKeyOld");
        } else {
            $eventClass = ModelEventEnum::$eventMaps[$name]['event'];
            /** @var class-string $eventClass */
            if (str_ends_with($name, '_deleted')) {
                $params = [$model->toArray()];
                if ($oldModel) {
                    $params[] = $oldModel->toArray();
                }
            } else {
                $params = [$model];
                if ($oldModel) {
                    $params[] = $oldModel;
                }
            }
            app(Dispatcher::class)->dispatch(new $eventClass(...$params));
            self::publishModel($name, (int) $model->getKey(), $model->toJson());
            Logger::writeWithContext('success fire_event in laravel, name: '.$name.', id: '.$model->getKey().', oldId: '.($oldModel ? $oldModel->getKey() : ''));
        }
    }

    /**
     * Publish a lightweight model-change event to Redis.
     *
     * Mirrors `publish_model_event()`.
     */
    public static function publishModel(string $event, int $id, string $json = ''): void
    {
        $channel = Env::get('CHANNEL_NAME_MODEL_EVENT', null);
        if (! empty($channel)) {
            NexusDB::redis()->publish($channel, json_encode(['event' => $event, 'id' => $id, 'json' => $json]));
        } else {
            Logger::writeWithContext("event: $event, id: $id, channel: ".(is_scalar($channel) ? (string) $channel : '').', channel is empty!', 'error');
        }
    }
}
