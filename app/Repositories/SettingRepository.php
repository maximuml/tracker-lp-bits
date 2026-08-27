<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Support\Cache;
use App\Support\Logger;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SettingRepository extends BaseRepository
{
    /**
     * @return array<string, mixed>
     */
    public static function getAll(): array
    {
        return Setting::getFromDb();
    }

    public static function getByName(string $name, mixed $default = null): mixed
    {
        return Setting::getByName($name, $default);
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return array<int|string, mixed>
     */
    public function getList(array $params)
    {
        $results = Setting::getFromDb();
        $prefix = $params['prefix'] ?? null;
        if ($prefix) {
            return [$prefix => Arr::get($results, $prefix, [])];
        }

        return $results;
    }

    /**
     * @param  array<int|string, mixed>  $params
     * @return mixed
     */
    public function store(array $params)
    {
        $settingModel = new Setting;
        $records = [];
        foreach ($params as $prefix => $nameValues) {
            if (! is_array($nameValues)) {
                throw new \InvalidArgumentException('Unsupported parameter format.');
            }
            foreach ($nameValues as $name => $value) {
                $valueArr = Arr::wrap($value);
                if (is_array($value)) {
                    $valueStr = json_encode($valueArr);
                } else {
                    $valueStr = Arr::first($valueArr);
                }
                $records[] = [
                    'name' => "$prefix.$name",
                    'value' => $valueStr,
                ];
            }
        }
        if (empty($records)) {
            Logger::writeWithContext((string) 'no values', (string) 'info', (bool) false);

            return true;
        }
        $result = DB::table($settingModel->getTable())->upsert($records, ['name'], ['value']);
        Logger::writeWithContext((string) sprintf('upsert %d settings, result: %s', count($records), $result ? 'true' : 'false'), (string) 'info', (bool) false);
        Cache::forgetWithLocales('nexus_settings_in_laravel');
        Cache::forgetWithLocales('nexus_settings_in_nexus');
        Cache::forgetWithLocales('setting_protected_forum');

        return $result;
    }

    /**
     * @param  array<string, mixed>  $nameAndValue
     */
    public static function saveBatch(string $prefix, array $nameAndValue, string $autoload = 'yes'): void
    {
        $prefix = strtolower($prefix);
        $datetimeNow = date('Y-m-d H:i:s');
        $records = [];

        foreach ($nameAndValue as $name => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $records[] = [
                'name' => "$prefix.$name",
                'value' => $value,
                'created_at' => $datetimeNow,
                'updated_at' => $datetimeNow,
                'autoload' => $autoload,
            ];
        }

        if (! empty($records)) {
            Setting::query()->upsert($records, ['name'], ['value', 'updated_at']);
        }

        Cache::clearSettings();
    }
}
