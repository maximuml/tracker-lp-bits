<?php
namespace App\Repositories;

use App\Models\Plugin;

class PluginRepository extends BaseRepository
{
    /**
     * @param  mixed  $action
     * @param  mixed  $id
     * @param  mixed  $force
     * @return  mixed
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
     * @return  void
     */
    private function doCronjob($action, $id, $force, $preStatus, $doingStatus)
    {
        $query = Plugin::query();
        if (!$force) {
            $query->where('status', $preStatus);
        }
        if ($id !== null) {
            $query->where("id", $id);
        }
        $list = $query->get();
        if ($list->isEmpty()) {
            do_log("No plugin need to be $action...");
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
     * @param  \App\Models\Plugin  $plugin
     * @return  mixed
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
            do_log("success install plugin: $packageName version: $version");
            $update = [
                'status' => Plugin::STATUS_NORMAL,
                'status_result' => $output,
                'installed_version' => $version
            ];
        } catch (\Throwable $throwable) {
            $update = [
                'status' => Plugin::STATUS_INSTALL_FAILED,
                'status_result' => $throwable->getMessage()
            ];
            do_log("fail install plugin: " . $packageName);
        } finally {
            $this->updateResult($plugin, $update);
        }

    }

    /**
     * @param  \App\Models\Plugin  $plugin
     * @return  mixed
     */
    public function doDelete(Plugin $plugin)
    {
        $plugin->update(['status' => Plugin::STATUS_DELETING]);
        $packageName = $plugin->package_name;
        $removeSuccess = true;
        try {
            $output = $this->execComposerRemove($plugin);
            do_log("success remove plugin: $packageName");
            $update = [
                'status' => Plugin::STATUS_NOT_INSTALLED,
                'status_result' => $output,
                'installed_version' => null,
            ];
        } catch (\Throwable $throwable) {
            $update = [
                'status' => Plugin::STATUS_DELETE_FAILED,
                'status_result' => $throwable->getMessage()
            ];
            $removeSuccess = false;
            do_log("fail remove plugin: " . $packageName);
        } finally {
            if ($removeSuccess) {
                $plugin->delete();
            } else {
                $this->updateResult($plugin, $update);
            }
        }

    }

    /**
     * @param  \App\Models\Plugin  $plugin
     * @return  mixed
     */
    public function doUpdate(Plugin $plugin)
    {
        $plugin->update(['status' => Plugin::STATUS_UPDATING]);
        $packageName = $plugin->package_name;
        try {
            $output = $this->execComposerUpdate($plugin);
            $this->execPluginInstall($plugin);
            $version = $this->getInstalledVersion($packageName);
            do_log("success update plugin: $packageName to version: $version");
            $update = [
                'status' => Plugin::STATUS_NORMAL,
                'status_result' => $output,
                'installed_version' => $version,
            ];
        } catch (\Throwable $throwable) {
            $update = [
                'status' => Plugin::STATUS_UPDATE_FAILED,
                'status_result' => $throwable->getMessage()
            ];
            do_log("fail update plugin: " . $packageName);
        } finally {
            $this->updateResult($plugin, $update);
        }

    }

    /**
     * @param  \App\Models\Plugin  $plugin
     * @return  mixed
     */
    private function getRepositoryKey(Plugin $plugin)
    {
        return str_replace("xiaomlove/nexusphp-", "", $plugin->package_name);
    }

    private function validatePackageName(string $packageName): void
    {
        if (!preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/i', $packageName)) {
            throw new \InvalidArgumentException("Invalid composer package name: $packageName");
        }
    }

    /**
     * @param  \App\Models\Plugin  $plugin
     * @return  mixed
     */
    private function execComposerConfig(Plugin $plugin)
    {
        $this->validatePackageName($plugin->package_name);
        $command = sprintf("composer config repositories.%s git %s", $this->getRepositoryKey($plugin), escapeshellarg((string) $plugin->remote_url));
        do_log("[COMPOSER_CONFIG]: $command");
        return $this->executeCommand($command);
    }

    /**
     * @param  \App\Models\Plugin  $plugin
     * @return  mixed
     */
    private function execComposerRequire(Plugin $plugin)
    {
        $this->validatePackageName($plugin->package_name);
        $command = sprintf("composer require %s", escapeshellarg($plugin->package_name));
        do_log("[COMPOSER_REQUIRE]: $command");
        return $this->executeCommand($command);
    }

    /**
     * @param  \App\Models\Plugin  $plugin
     * @return  mixed
     */
    private function execComposerRemove(Plugin $plugin)
    {
        $this->validatePackageName($plugin->package_name);
        $command = sprintf("composer remove %s", escapeshellarg($plugin->package_name));
        do_log("[COMPOSER_REMOVE]: $command");
        return $this->executeCommand($command);
    }

    /**
     * @param  \App\Models\Plugin  $plugin
     * @return  mixed
     */
    private function execComposerUpdate(Plugin $plugin)
    {
        $this->validatePackageName($plugin->package_name);
        $command = sprintf("composer update %s", escapeshellarg($plugin->package_name));
        do_log("[COMPOSER_UPDATE]: $command");
        return $this->executeCommand($command);
    }

    /**
     * @param  \App\Models\Plugin  $plugin
     * @return  mixed
     */
    private function execPluginInstall(Plugin $plugin)
    {
        $this->validatePackageName($plugin->package_name);
        $command = sprintf("php artisan plugin install %s", escapeshellarg($plugin->package_name));
        do_log("[PLUGIN_INSTALL]: $command");
        return $this->executeCommand($command);
    }

    /**
     * @param  \App\Models\Plugin  $plugin
     * @param  array<int|string, mixed>  $update
     * @return  mixed
     */
    private function updateResult(Plugin $plugin, array $update)
    {
        $update['status_result'] = $update['status_result'] . "\n\nREQUEST_ID: " . nexus()->getRequestId();
        do_log("[UPDATE]: " . json_encode($update));
        $plugin->update($update);
    }

    /**
     * @param  mixed  $packageName
     * @return  mixed
     */
    public function getInstalledVersion($packageName)
    {
        $this->validatePackageName((string) $packageName);
        $command = sprintf('composer info | grep -F %s', escapeshellarg($packageName));
        $result = $this->executeCommand($command);
        $parts = preg_split("/[\s]+/", trim($result));
        $version = $parts[1] ?? '';
        if (str_contains($version, 'dev')) {
            $version .= " " . ($parts[2] ?? '');
        }
        return $version;
    }


}
