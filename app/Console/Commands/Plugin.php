<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Nexus\Plugin\BasePlugin;

class Plugin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'plugin {action} {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Plugin management, arguments: action plugin';

    /**
     * Execute the console command.
     * @return  int
     */
    public function handle()
    {
        $plugin = new \Nexus\Plugin\Plugin();
        $action = $this->argument('action');
        $name = $this->argument('name');
        /** @var BasePlugin $mainClass */
        $mainClass = $plugin->getMainClass($name);
        if (!$mainClass) {
            $this->error("Can not find plugin: $name");
            return 1;
        }
        try {
            $mainClass->checkMainApplicationVersion(false);
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());
            return 1;
        }
        $callable = [$mainClass, $action];
        /** @var callable $callable */
        if (in_array($action, ['install', 'uninstall'], true)) {
            call_user_func($callable);
        } else {
            $this->error("Not support action: $action");
            return 1;
        }
        $log = sprintf("[%s], %s plugin: %s successfully !", \Nexus\Nexus::instance()->getRequestId(), $action, $name);
        $this->info($log);
        \App\Support\Logger::writeWithContext((string) $log, (string) 'info', (bool) false);
        return 0;
    }
}
