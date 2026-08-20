<?php

namespace App\Console\Commands;

use App\Enums\ModelEventEnum;
use App\Support\Events;
use App\Support\Logger;
use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Nexus\Database\NexusDB;
use Symfony\Component\Console\Command\Command as CommandAlias;

class FireEvent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'event:fire {--name=} {--idKey=} {--idKeyOld=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fire an event, options: --name, --idKey --idKeyOld';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $name = (string) $this->option('name');
        $idKey = (string) $this->option('idKey');
        $idKeyOld = (string) $this->option('idKeyOld');
        $log = "FireEvent, name: $name, idKey: $idKey, idKeyOld: $idKeyOld";
        $this->info("$log, begin ...");
        if ($name === '' || ! isset(ModelEventEnum::$eventMaps[$name])) {
            $this->warn("$log, no event match this name");

            return CommandAlias::FAILURE;
        }
        $eventName = ModelEventEnum::$eventMaps[$name]['event'];
        /** @var class-string $eventName */
        /** @var class-string<Model> $modelClassName */
        $modelClassName = ModelEventEnum::$eventMaps[$name]['model'];
        $modelBasic = new $modelClassName;
        $rawData = NexusDB::cache_get($idKey);
        $modelData = is_string($rawData) ? unserialize($rawData) : $rawData;
        if (! is_array($modelData)) {
            $this->error("$log, invalid modelData");

            return CommandAlias::FAILURE;
        }
        $useArray = str_ends_with($name, '_deleted');
        $model = $modelBasic->newInstance($modelData, true);
        // 由于 id 不属于 fillable，初始化新对象时是没有值的
        $model->setAttribute('id', $modelData['id']);
        $params = [$useArray ? $modelData : $model];
        if ($idKeyOld !== '') {
            $rawOldData = NexusDB::cache_get($idKeyOld);
            $modelOldData = is_string($rawOldData) ? unserialize($rawOldData) : $rawOldData;
            if (! is_array($modelOldData)) {
                $this->error("$log, invalid modelOldData");

                return CommandAlias::FAILURE;
            }
            $modelOld = $modelBasic->newInstance($modelOldData, true);
            $modelOld->setAttribute('id', $modelOldData['id']);
            $params[] = $useArray ? $modelOldData : $modelOld;
        }
        $result = app(Dispatcher::class)->dispatch(new $eventName(...$params));
        $log .= ', success call dispatch, result: '.var_export($result, true);
        Events::publishModel($name, $model->getKey(), '');
        $this->info($log);
        Logger::writeWithContext((string) $log, (string) 'info', (bool) false);

        return CommandAlias::SUCCESS;
    }
}
