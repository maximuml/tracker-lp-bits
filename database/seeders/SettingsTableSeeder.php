<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class SettingsTableSeeder extends Seeder
{
    public function run()
    {
        $settings = require __DIR__.'/../../app/Support/Install/settings.default.php';

        foreach ($settings as $prefix => $group) {
            foreach ($group as $key => $value) {
                Setting::updateOrCreate(
                    ['name' => "{$prefix}.{$key}"],
                    [
                        'value' => is_array($value) ? json_encode($value) : $value,
                        'autoload' => 'yes',
                    ]
                );
            }
        }

        // Freshly seeded settings must be visible immediately, even if the cache
        // is still warm from a previous install/seed cycle.
        Cache::forget('nexus_settings_in_laravel');
        Cache::forget('nexus_settings_in_nexus');
    }
}
