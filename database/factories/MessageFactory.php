<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sender' => User::factory(),
            'receiver' => User::factory(),
            'added' => now()->toDateTimeString(),
            'subject' => $this->faker->sentence(4),
            'msg' => $this->faker->paragraphs(2, true),
            'unread' => 'yes',
            'location' => 1,
            'saved' => 'no',
        ];
    }

    /**
     * Set the sender and receiver explicitly.
     *
     * @return $this
     */
    public function between(User $sender, User $receiver): self
    {
        return $this->state(fn (array $attributes) => [
            'sender' => $sender->id,
            'receiver' => $receiver->id,
        ]);
    }

    /**
     * Mark the message as already read.
     *
     * @return $this
     */
    public function read(): self
    {
        return $this->state(fn (array $attributes) => [
            'unread' => 'no',
        ]);
    }
}
