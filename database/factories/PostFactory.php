<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = $this->faker->paragraphs(3, true);

        return [
            'topicid' => Topic::factory(),
            'userid' => User::factory(),
            'added' => now()->toDateTimeString(),
            'body' => $body,
            'ori_body' => $body,
            'editedby' => null,
            'editdate' => null,
        ];
    }

    /**
     * Use the given topic.
     *
     * @return $this
     */
    public function topic(Topic $topic): self
    {
        return $this->state(fn (array $attributes) => [
            'topicid' => $topic->id,
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
}
