<?php

namespace Database\Factories;

use App\Models\Forum;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ForumFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Forum::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sort' => $this->faker->numberBetween(0, 100),
            'name' => $this->faker->unique()->words(3, true),
            'description' => $this->faker->sentence(),
            'minclassread' => intval(User::CLASS_USER),
            'minclasswrite' => intval(User::CLASS_USER),
            'postcount' => 0,
            'topiccount' => 0,
            'minclasscreate' => intval(User::CLASS_USER),
            'forid' => 0,
        ];
    }

    /**
     * Set the required class for read/write/create.
     *
     * @return $this
     */
    public function minClass(int $class): self
    {
        return $this->state(fn (array $attributes) => [
            'minclassread' => $class,
            'minclasswrite' => $class,
            'minclasscreate' => $class,
        ]);
    }
}
