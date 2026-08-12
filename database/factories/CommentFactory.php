<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = $this->faker->paragraph();

        return [
            'user' => User::factory(),
            'torrent' => Torrent::factory(),
            'added' => now()->toDateTimeString(),
            'text' => $text,
            'ori_text' => $text,
            'editedby' => 0,
            'editdate' => null,
            'offer' => 0,
            'anonymous' => 'no',
        ];
    }

    /**
     * Use the given torrent.
     *
     * @return $this
     */
    public function torrent(Torrent $torrent): self
    {
        return $this->state(fn (array $attributes) => [
            'torrent' => $torrent->id,
        ]);
    }

    /**
     * Use the given user as the comment author.
     *
     * @return $this
     */
    public function author(User $user): self
    {
        return $this->state(fn (array $attributes) => [
            'user' => $user->id,
        ]);
    }
}
