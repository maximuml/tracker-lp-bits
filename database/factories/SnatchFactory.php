<?php

namespace Database\Factories;

use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SnatchFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Snatch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'torrentid' => Torrent::factory(),
            'userid' => User::factory(),
            'ip' => $this->faker->ipv4(),
            'port' => $this->faker->numberBetween(1025, 65535),
            'uploaded' => $this->faker->numberBetween(0, 1073741824),
            'downloaded' => $this->faker->numberBetween(0, 1073741824),
            'to_go' => 0,
            'seedtime' => $this->faker->numberBetween(0, 86400),
            'leechtime' => $this->faker->numberBetween(0, 86400),
            'last_action' => now()->toDateTimeString(),
            'startdat' => now()->toDateTimeString(),
            'completedat' => null,
            'finished' => 'no',
            'hit_and_run_id' => 0,
            'buy_log_id' => 0,
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
            'to_go' => $torrent->size,
        ]);
    }

    /**
     * Mark the snatch as finished.
     *
     * @return $this
     */
    public function finished(): self
    {
        return $this->state(fn (array $attributes) => [
            'finished' => 'yes',
            'to_go' => 0,
            'completedat' => now()->toDateTimeString(),
        ]);
    }
}
