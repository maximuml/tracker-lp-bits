<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\SearchBox;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mode' => SearchBox::factory(),
            'class_name' => 'c_' . $this->faker->regexify('[a-z]{8}'),
            'name' => $this->faker->unique()->word(),
            'image' => 'catsprites.png',
            'sort_index' => $this->faker->numberBetween(0, 20),
            'icon_id' => 0,
        ];
    }

    /**
     * Use the given search box mode id.
     *
     * @return $this
     */
    public function mode(int $mode): self
    {
        return $this->state(fn (array $attributes) => [
            'mode' => $mode,
        ]);
    }
}
