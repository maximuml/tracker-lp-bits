<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

class TagFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Tag::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->uuid(),
            'color' => $this->faker->hexColor(),
            'priority' => $this->faker->numberBetween(0, 100),
            'mode' => 0,
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
