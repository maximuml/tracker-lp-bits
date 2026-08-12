<?php

namespace Database\Factories;

use App\Models\User;
use App\Support\Token;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    private static string $defaultStyleSheet = "";

    private static int $sequence = 1;

    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $password = "123456";
        $secret = mksecret();
        $passhash = md5($secret . $password . $secret);
        if (self::$defaultStyleSheet == "") {
            self::$defaultStyleSheet = \App\Support\Config\SiteConfig::current()->main->defStylesheet();
        }
        $username = sprintf("%s_%s", microtime(true), self::$sequence);
        $email = sprintf("%s@example.net", $username);
        self::$sequence++;
        $randNum = random_int(1, 10);
        if ($randNum >= 8) {
            $class = random_int(intval(User::CLASS_POWER_USER), intval(User::CLASS_SYSOP));
        } else {
            $class = User::CLASS_USER;
        }
        return [
            'username' => $username,
            'email' => $email,
            'secret' => mksecret(),
            'editsecret' => "",
            'passhash' => $passhash,
            'passkey' => Token::randomHex(16),
            'stylesheet' => self::$defaultStyleSheet,
            'added' => now()->toDateTimeString(),
            'status' => User::STATUS_CONFIRMED,
            'class' => $class,
            'enabled' => User::ENABLED_YES,
        ];
    }

    /**
     * Use the given user class.
     *
     * @return $this
     */
    public function class(int $class): self
    {
        return $this->state(fn (array $attributes) => [
            'class' => $class,
        ]);
    }

    /**
     * Use a specific passkey.
     *
     * @return $this
     */
    public function withPasskey(string $passkey): self
    {
        return $this->state(fn (array $attributes) => [
            'passkey' => $passkey,
        ]);
    }

    /**
     * Mark the user as an administrator.
     *
     * @return $this
     */
    public function admin(): self
    {
        return $this->class(intval(User::CLASS_SYSOP));
    }

    /**
     * Mark the user as disabled.
     *
     * @return $this
     */
    public function disabled(): self
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => User::ENABLED_NO,
        ]);
    }
}
