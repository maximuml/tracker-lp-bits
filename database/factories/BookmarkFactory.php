<?php

namespace Database\Factories;

use App\Models\Bookmark;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookmarkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Bookmark::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'userid' => User::factory(),
            'torrentid' => Torrent::factory(),
        ];
    }

    /**
     * Use the given user and torrent.
     *
     * @return $this
     */
    public function forUserAndTorrent(User $user, Torrent $torrent): self
    {
        return $this->state(fn (array $attributes) => [
            'userid' => $user->id,
            'torrentid' => $torrent->id,
        ]);
    }
}
