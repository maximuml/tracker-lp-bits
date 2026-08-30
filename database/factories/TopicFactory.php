<?php

namespace Database\Factories;

use App\Models\Forum;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TopicFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Topic::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'userid' => User::factory(),
            'subject' => $this->faker->sentence(4),
            'locked' => false,
            'forumid' => Forum::factory(),
            'firstpost' => 0,
            'lastpost' => 0,
            'sticky' => false,
            'hlcolor' => 0,
            'views' => 0,
        ];
    }

    /**
     * Use the given forum.
     *
     * @return $this
     */
    public function forum(Forum $forum): self
    {
        return $this->state(fn (array $attributes) => [
            'forumid' => $forum->id,
        ]);
    }

    /**
     * Use the given author.
     *
     * @return $this
     */
    public function author(User $user): self
    {
        return $this->state(fn (array $attributes) => [
            'userid' => $user->id,
        ]);
    }

    /**
     * Mark the topic as sticky.
     *
     * @return $this
     */
    public function sticky(): self
    {
        return $this->state(fn (array $attributes) => [
            'sticky' => true,
        ]);
    }
}
