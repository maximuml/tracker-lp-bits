<?php

namespace Database\Factories;

use App\Enums\UserClass;
use App\Enums\UserEnabled;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\PasswordHasher;
use App\Support\Token;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    private static string $defaultStyleSheet = '';

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
    public function configure(): self
    {
        return $this->afterCreating(function (User $user) {
            $user->refresh();
        });
    }

    public function definition(): array
    {
        $password = '123456';
        $secret = Token::randomHex((int) 20);
        $passhash = md5($secret.$password.$secret);
        if (self::$defaultStyleSheet == '') {
            self::$defaultStyleSheet = SiteConfig::current()->main->defStylesheet();
        }
        $username = sprintf('%s_%s', microtime(true), self::$sequence);
        $email = sprintf('%s@example.net', $username);
        self::$sequence++;
        $randNum = random_int(1, 10);
        if ($randNum >= 8) {
            $class = random_int(UserClass::POWER_USER->value, UserClass::SYSOP->value);
        } else {
            $class = UserClass::USER->value;
        }

        return [
            'username' => $username,
            'email' => $email,
            'secret' => $secret,
            'editsecret' => '',
            'passhash' => $passhash,
            'passhash_algo' => PasswordHasher::ALGO_MD5,
            'passkey' => Token::randomHex(16),
            'stylesheet' => self::$defaultStyleSheet,
            'added' => now()->toDateTimeString(),
            'status' => UserStatus::CONFIRMED->value,
            'class' => $class,
            'enabled' => UserEnabled::YES->value,
            'timetype' => 'timealive',
            'downloadpos' => true,
            'avatars' => true,
            'uploaded' => 0,
            'downloaded' => 0,
            'seedbonus' => 0.0,
            'parked' => false,
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
        return $this->class(UserClass::SYSOP->value);
    }

    /**
     * Mark the user as disabled.
     *
     * @return $this
     */
    public function disabled(): self
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => UserEnabled::NO->value,
        ]);
    }
}
