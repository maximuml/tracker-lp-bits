<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\Torrent;
use Illuminate\Database\Eloquent\Factories\Factory;

class FileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = File::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'torrent' => Torrent::factory(),
            'filename' => $this->faker->filePath(),
            'size' => $this->faker->numberBetween(512, 1073741824),
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
}
