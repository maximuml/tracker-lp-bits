<?php

namespace App\Jobs;

use App\Support\Json;
use App\Support\Logger;
use Illuminate\Bus\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;
use Nexus\Plugin\Plugin;

class MaintainPluginState
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $enabled = Plugin::listEnabled();
        $key = 'nexus_plugin_enabled';
        Redis::connection()->client()->del($key);
        $nowStr = now()->toDateTimeString();
        foreach ($enabled as $name => $value) {
            Redis::connection()->client()->hSet($key, $name, $nowStr);
        }
        Logger::writeWithContext((string) ("{$key}: ".Json::encode($enabled)), (string) 'info', (bool) false);
    }
}
