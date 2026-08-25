<?php

namespace App\Jobs;

use App\Enums\ModelEventEnum;
use App\Support\Events;
use App\Support\Logger;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class FireEvent implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $name, public string $idKey, public string $idKeyOld = '')
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $name = $this->name;
        $idKey = $this->idKey;
        $idKeyOld = $this->idKeyOld;
        $log = "Job FireEvent, name: $name, idKey: $idKey, idKeyOld: $idKeyOld";
        Logger::writeWithContext((string) "{$log}, begin ...", (string) 'info', (bool) false);
        if (isset(ModelEventEnum::$eventMaps[$name])) {
            $eventName = ModelEventEnum::$eventMaps[$name]['event'];
            $modelClassName = ModelEventEnum::$eventMaps[$name]['model'];
            /** @var class-string<Model> $modelClassName */
            $modelBasic = new $modelClassName;
            $rawModelData = Cache::get($idKey);
            $modelData = is_string($rawModelData) ? json_decode($rawModelData, true) : $rawModelData;
            if (! is_array($modelData)) {
                Logger::writeWithContext((string) "{$log}, invalid model data", (string) 'error', (bool) false);

                return;
            }
            $useArray = str_ends_with($name, '_deleted');
            $model = $modelBasic->newInstance($modelData, true);
            // 由于 id 不属于 fillable，初始化新对象时是没有值的
            $model->setAttribute('id', $modelData['id'] ?? 0);
            $params = [$useArray ? $modelData : $model];
            if ($idKeyOld) {
                $rawModelOldData = Cache::get($idKeyOld);
                $modelOldData = is_string($rawModelOldData) ? json_decode($rawModelOldData, true) : $rawModelOldData;
                if (is_array($modelOldData)) {
                    $modelOld = $modelBasic->newInstance($modelOldData, true);
                    $modelOld->setAttribute('id', $modelOldData['id'] ?? 0);
                    $params[] = $useArray ? $modelOldData : $modelOld;
                }
            }
            /** @var class-string $eventName */
            $result = app(Dispatcher::class)->dispatch(new $eventName(...$params));
            $log .= ', success call dispatch, result: '.var_export($result, true);
            Events::publishModel($name, (int) $model->getKey(), $model->toJson());
        } else {
            $log .= ', no event match this name';
        }
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
    }
}
