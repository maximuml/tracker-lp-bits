<?php

namespace Database\Factories;

use App\Models\Peer;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Token;
use App\ValueObjects\PeerId;
use Illuminate\Database\Eloquent\Factories\Factory;

class PeerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Peer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seeder = $this->faker->randomElement([1, 0]);

        return [
            'torrent' => Torrent::factory(),
            'peer_id' => PeerId::fromBinary(random_bytes(20))->toBinary(),
            'ip' => $this->faker->ipv4(),
            'port' => $this->faker->numberBetween(1025, 65535),
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => $seeder === 1 ? 0 : $this->faker->numberBetween(0, 1073741824),
            'seeder' => $seeder,
            'started' => now()->toDateTimeString(),
            'last_action' => now()->toDateTimeString(),
            'prev_action' => now()->toDateTimeString(),
            'connectable' => 1,
            'userid' => User::factory(),
            'agent' => substr($this->faker->userAgent(), 0, 60),
            'finishedat' => 0,
            'downloadoffset' => 0,
            'passkey' => Token::randomHex(16),
            'ipv4' => '',
            'ipv6' => '',
        ];
    }

    /**
     * Use the given user and inherit their passkey.
     *
     * @return $this
     */
    public function user(User $user): self
    {
        return $this->state(fn (array $attributes) => [
            'userid' => $user->id,
            'passkey' => $user->passkey ?: Token::randomHex(16),
        ]);
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
            'to_go' => $torrent->size,
        ]);
    }

    /**
     * Mark the peer as a seeder.
     *
     * @return $this
     */
    public function seeder(): self
    {
        return $this->state(fn (array $attributes) => [
            'seeder' => 1,
            'to_go' => 0,
        ]);
    }

    /**
     * Mark the peer as a leecher.
     *
     * @return $this
     */
    public function leecher(): self
    {
        return $this->state(fn (array $attributes) => [
            'seeder' => 0,
            'to_go' => $this->faker->numberBetween(1, 1073741824),
        ]);
    }
}
