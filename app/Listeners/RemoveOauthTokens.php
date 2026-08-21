<?php

namespace App\Listeners;

use App\Support\Logger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Laravel\Passport\Passport;

class RemoveOauthTokens implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(object $event): void
    {
        $uid = 0;
        if (property_exists($event, 'model') && $event->model instanceof Model) {
            $uid = (int) $event->model->getKey();
        }
        $modelNames = [
            Passport::$authCodeModel,
            Passport::$tokenModel,
        ];
        foreach ($modelNames as $name) {
            /** @var class-string<Model> $name */
            $model = new $name;
            $model::query()->where('user_id', $uid)->forceDelete();
        }
        Logger::writeWithContext((string) sprintf('success remove user: %d oauth tokens related.', $uid), (string) 'info', (bool) false);
    }
}
