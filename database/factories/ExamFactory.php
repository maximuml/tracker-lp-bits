<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ExamStatus;
use App\Enums\ExamType;
use App\Models\Exam;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Exam> */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => $this->faker->sentence(),
            'description' => $this->faker->paragraph(),
            'begin' => null,
            'end' => null,
            'duration' => 30,
            'status' => ExamStatus::ENABLED->value,
            'is_discovered' => 0,
            'filters' => ['user_class' => [1]],
            'indexes' => [['index' => 1, 'checked' => true, 'require_value' => '100']],
            'priority' => 0,
            'recurring' => null,
            'type' => ExamType::EXAM->value,
            'success_reward_bonus' => 100,
            'fail_deduct_bonus' => 50,
            'max_user_count' => 0,
            'background_color' => '',
        ];
    }
}
