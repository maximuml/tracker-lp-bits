<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BusinessType;
use App\Models\BonusLogs;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BonusLogs> */
class BonusLogsFactory extends Factory
{
    protected $model = BonusLogs::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'uid' => User::factory(),
            'business_type' => BusinessType::POST_REWARD->value,
            'old_total_value' => 0.0,
            'value' => 10.0,
            'new_total_value' => 10.0,
            'comment' => 'Test bonus log',
        ];
    }
}
