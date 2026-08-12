<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Token;
use App\ValueObjects\InfoHash;
use Illuminate\Database\Eloquent\Factories\Factory;

class TorrentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Torrent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['single', 'multi']);

        return [
            'name' => $this->faker->sentence(3),
            'filename' => $this->faker->uuid() . '.torrent',
            'save_as' => $this->faker->slug(),
            'cover' => '',
            'small_descr' => '',
            'category' => Category::factory(),
            'source' => 0,
            'medium' => 0,
            'codec' => 0,
            'standard' => 0,
            'processing' => 0,
            'audiocodec' => 0,
            'size' => $this->faker->numberBetween(1024, 1073741824),
            'added' => now()->toDateTimeString(),
            'type' => $type,
            'numfiles' => $type === 'single' ? 1 : $this->faker->numberBetween(2, 50),
            'comments' => 0,
            'views' => 0,
            'hits' => 0,
            'times_completed' => 0,
            'leechers' => 0,
            'seeders' => 0,
            'last_action' => now()->toDateTimeString(),
            'visible' => Torrent::VISIBLE_YES,
            'banned' => Torrent::BANNED_NO,
            'owner' => User::factory(),
            'sp_state' => 1,
            'promotion_time_type' => 0,
            'promotion_until' => null,
            'anonymous' => 'no',
            'url' => null,
            'pos_state' => Torrent::POS_STATE_STICKY_NONE,
            'cache_stamp' => 0,
            'last_reseed' => null,
            'hr' => 0,
            'approval_status' => 0,
            'price' => 0,
            'info_hash' => InfoHash::fromBinary(random_bytes(20))->toBinary(),
            'pieces_hash' => Token::randomHex(20),
        ];
    }

    /**
     * Use a specific owner.
     *
     * @return $this
     */
    public function owner(User $user): self
    {
        return $this->state(fn (array $attributes) => [
            'owner' => $user->id,
        ]);
    }

    /**
     * Mark the torrent as banned.
     *
     * @return $this
     */
    public function banned(): self
    {
        return $this->state(fn (array $attributes) => [
            'banned' => Torrent::BANNED_YES,
        ]);
    }

    /**
     * Mark the torrent as not visible.
     *
     * @return $this
     */
    public function invisible(): self
    {
        return $this->state(fn (array $attributes) => [
            'visible' => Torrent::VISIBLE_NO,
        ]);
    }
}
