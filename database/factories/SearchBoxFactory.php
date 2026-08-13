<?php

namespace Database\Factories;

use App\Models\SearchBox;
use Illuminate\Database\Eloquent\Factories\Factory;

class SearchBoxFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SearchBox::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'catsperrow' => $this->faker->numberBetween(4, 10),
            'catpadding' => 25,
            'showsubcat' => 1,
            'showsource' => 1,
            'showmedium' => 1,
            'showcodec' => 1,
            'showstandard' => 1,
            'showprocessing' => 1,
            'showaudiocodec' => 1,
            'section_name' => ['en' => $this->faker->words(2, true)],
            'custom_fields' => null,
            'custom_fields_display_name' => '',
            'custom_fields_display' => null,
            'extra' => null,
        ];
    }

    /**
     * Configure the search box as the default one.
     *
     * @return $this
     */
    public function default(): self
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Default',
        ]);
    }
}
