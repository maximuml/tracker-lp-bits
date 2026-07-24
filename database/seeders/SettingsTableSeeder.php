<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsTableSeeder extends Seeder
{
    public function run()
    {
        $settings = require __DIR__ . '/../../nexus/Install/settings.default.php';

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
    }
}
