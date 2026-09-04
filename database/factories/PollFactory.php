<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Poll;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Poll> */
class PollFactory extends Factory
{
    protected $model = Poll::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'question' => $this->faker->sentence(),
            'option0' => $this->faker->word(),
            'option1' => $this->faker->word(),
            'option2' => '',
            'option3' => '',
            'option4' => '',
            'option5' => '',
            'option6' => '',
            'option7' => '',
            'option8' => '',
            'option9' => '',
            'option10' => '',
            'option11' => '',
            'option12' => '',
            'option13' => '',
            'option14' => '',
            'option15' => '',
            'option16' => '',
            'option17' => '',
            'option18' => '',
            'option19' => '',
        ];
    }
}
