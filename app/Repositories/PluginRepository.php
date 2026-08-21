<?php

namespace App\Repositories;

use App\Models\Plugin;
use App\Support\Logger;
use Nexus\Nexus;

class PluginRepository extends BaseRepository
{
    /**
     * @param  mixed  $action
     * @param  mixed  $id
     * @param  mixed  $force
     * @return mixed
     */
    public function cronjob($action = null, $id = null, $force = false)
    {
        if ($action == 'install' || $action === null) {
            $this->doCronjob('install', $id, $force, Plugin::STATUS_PRE_INSTALL, Plugin::STATUS_INSTALLING);
        }
        if ($action == 'delete' || $action === null) {
            $this->doCronjob('delete', $id, $force, Plugin::STATUS_PRE_DELETE, Plugin::STATUS_DELETING);
        }
        if ($action == 'update' || $action === null) {
            $this->doCronjob('update', $id, $force, Plugin::STATUS_PRE_UPDATE, Plugin::STATUS_UPDATING);
        }
    }

    /**
     * @param  mixed  $action
     * @param  mixed  $id
     * @param  mixed  $force
     * @param  mixed  $preStatus
     * @param  mixed  $doingStatus
     * @return void
     */
    private function doCronjob($action, $id, $force, $preStatus, $doingStatus)
    {
        $query = Plugin::query();
        if (! $force) {
            $query->where('status', $preStatus);
        }
        if ($id !== null) {
            $query->where('id', $id);
        }
        $list = $query->get();
        if ($list->isEmpty()) {
            Logger::writeWithContext((string) "No plugin need to be {$action}...", (string) 'info', (bool) false);

            return;
        }
        $idArr = $list->pluck('id')->toArray();
        Plugin::query()->whereIn('id', $idArr)->update(['status' => $doingStatus]);
        foreach ($list as $item) {
            match ($action) {
                'install' => $this->doInstall($item),
                'update' => $this->doUpdate($item),
                'delete' => $this->doDelete($item),
                default => throw new \InvalidArgumentException("Invalid action: $action")
            };
        }
    }

    /**
     * @return mixed
     */
    public function doInstall(Plugin $plugin)
    {
        $plugin->update(['status' => Plugin::STATUS_INSTALLING]);
        $packageName = $plugin->package_name;
        try {
            $this->execComposerConfig($plugin);
            $this->execComposerRequire($plugin);
            $output = $this->execPluginInstall($plugin);
            $version = $this->getInstalledVersion($packageName);
            Logger::writeWithContext((string) "success install plugin: {$packageName} version: {$version}", (string) 'info', (bool) false);
            $update = [
                'status' => Plugin::STATUS_NORMAL,
                'status_result' => $output,
                'installed_version' => $version,
            ];
        } catch (\Throwable $throwable) {
            $update = [
                'status' => Plugin::STATUS_INSTALL_FAILED,
                'status_result' => $throwable->getMessage(),
            ];
            Logger::writeWithContext((string) ('fail install plugin: '.$packageName), (string) 'info', (bool) false);
        } finally {
            $this->updateResult($plugin, $update);
        }

    }

    /**
     * @return mixed
     */
    public function doDelete(Plugin $plugin)
    {
        $plugin->update(['status' => Plugin::STATUS_DELETING]);
        $packageName = $plugin->package_name;
        $removeSuccess = true;
        try {
            $output = $this->execComposerRemove($plugin);
            Logger::writeWithContext((string) "success remove plugin: {$packageName}", (string) 'info', (bool) false);
            $update = [
                'status' => Plugin::STATUS_NOT_INSTALLED,
                'status_result' => $output,
                'installed_version' => null,
            ];
        } catch (\Throwable $throwable) {
            $update = [
                'status' => Plugin::STATUS_DELETE_FAILED,
                'status_result' => $throwable->getMessage(),
            ];
            $removeSuccess = false;
            Logger::writeWithContext((string) ('fail remove plugin: '.$packageName), (string) 'info', (bool) false);
        } finally {
            if ($removeSuccess) {
                $plugin->delete();
            } else {
                $this->updateResult($plugin, $update);
            }
        }

    }

    /**
     * @return mixed
     */
    public function doUpdate(Plugin $plugin)
    {
        $plugin->update(['status' => Plugin::STATUS_UPDATING]);
        $packageName = $plugin->package_name;
        try {
            $output = $this->execComposerUpdate($plugin);
            $this->execPluginInstall($plugin);
            $version = $this->getInstalledVersion($packageName);
            Logger::writeWithContext((string) "success update plugin: {$packageName} to version: {$version}", (string) 'info', (bool) false);
            $update = [
                'status' => Plugin::STATUS_NORMAL,
                'status_result' => $output,
                'installed_version' => $version,
            ];
        } catch (\Throwable $throwable) {
            $update = [
                'status' => Plugin::STATUS_UPDATE_FAILED,
                'status_result' => $throwable->getMessage(),
            ];
            Logger::writeWithContext((string) ('fail update plugin: '.$packageName), (string) 'info', (bool) false);
        } finally {
            $this->updateResult($plugin, $update);
        }

    }

    /**
     * @return mixed
     */
    private function getRepositoryKey(Plugin $plugin)
    {
        return str_replace('xiaomlove/nexusphp-', '', $plugin->package_name);
    }

    private function validatePackageName(string $packageName): void
    {
        if (! preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/i', $packageName)) {
            throw new \InvalidArgumentException("Invalid composer package name: $packageName");
        }
    }

    /**
     * @return mixed
     */
    private function execComposerConfig(Plugin $plugin)
    {
        $this->validatePackageName($plugin->package_name);
        $command = sprintf('composer config repositories.%s git %s', $this->getRepositoryKey($plugin), escapeshellarg((string) $plugin->remote_url));
        Logger::writeWithContext((string) "[COMPOSER_CONFIG]: {$command}", (string) 'info', (bool) false);

        return $this->executeCommand($command);
    }

    /**
     * @return mixed
     */
    private function execComposerRequire(Plugin $plugin)
    {
        $this->validatePackageName($plugin->package_name);
        $command = sprintf('composer require %s', escapeshellarg($plugin->package_name));
        Logger::writeWithContext((string) "[COMPOSER_REQUIRE]: {$command}", (string) 'info', (bool) false);

        return $this->executeCommand($command);
    }

    /**
     * @return mixed
     */
    private function execComposerRemove(Plugin $plugin)
    {
        $this->validatePackageName($plugin->package_name);
        $command = sprintf('composer remove %s', escapeshellarg($plugin->package_name));
        Logger::writeWithContext((string) "[COMPOSER_REMOVE]: {$command}", (string) 'info', (bool) false);

        return $this->executeCommand($command);
    }

    /**
     * @return mixed
     */
    private function execComposerUpdate(Plugin $plugin)
    {
        $this->validatePackageName($plugin->package_name);
        $command = sprintf('composer update %s', escapeshellarg($plugin->package_name));
        Logger::writeWithContext((string) "[COMPOSER_UPDATE]: {$command}", (string) 'info', (bool) false);

        return $this->executeCommand($command);
    }

    /**
     * @return mixed
     */
    private function execPluginInstall(Plugin $plugin)
    {
        $this->validatePackageName($plugin->package_name);
        $command = sprintf('php artisan plugin install %s', escapeshellarg($plugin->package_name));
        Logger::writeWithContext((string) "[PLUGIN_INSTALL]: {$command}", (string) 'info', (bool) false);

        return $this->executeCommand($command);
    }

    /**
     * @param  array<string, mixed>  $update
     * @return mixed
     */
    private function updateResult(Plugin $plugin, array $update)
    {
        $update['status_result'] = $update['status_result']."\n\nREQUEST_ID: ".Nexus::instance()->getRequestId();
        Logger::writeWithContext((string) ('[UPDATE]: '.json_encode($update)), (string) 'info', (bool) false);
        $plugin->update($update);
    }

    /**
     * @param  mixed  $packageName
     * @return mixed
     */
    public function getInstalledVersion($packageName)
    {
        $this->validatePackageName((string) $packageName);
        $command = sprintf('composer info | grep -F %s', escapeshellarg($packageName));
        $result = $this->executeCommand($command);
        if (! is_string($result)) {
            return '';
        }
        $parts = preg_split("/[\s]+/", trim($result));
        $version = $parts[1] ?? '';
        if (str_contains($version, 'dev')) {
            $version .= ' '.($parts[2] ?? '');
        }

        return $version;
    }
}
