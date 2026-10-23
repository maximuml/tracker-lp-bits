<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Captcha\Drivers;

use App\Services\Captcha\Drivers\TurnstileCaptchaDriver;
use App\Services\Captcha\Exceptions\CaptchaValidationException;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Pins the Turnstile driver contract — including the once-per-request
 * script injection flag that must be resettable between worker cycles.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TurnstileCaptchaDriverTest extends TestCase
{
    private TurnstileCaptchaDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        TurnstileCaptchaDriver::resetState();
        $this->driver = new TurnstileCaptchaDriver([
            'site_key' => 'site-key',
            'secret_key' => 'secret-key',
        ]);
    }

    protected function tearDown(): void
    {
        TurnstileCaptchaDriver::resetState();

        parent::tearDown();
    }

    public function test_is_enabled_requires_both_keys(): void
    {
        $this->assertTrue($this->driver->isEnabled());
        $this->assertFalse((new TurnstileCaptchaDriver([]))->isEnabled());
        $this->assertFalse((new TurnstileCaptchaDriver(['site_key' => 'k']))->isEnabled());
        $this->assertFalse((new TurnstileCaptchaDriver(['secret_key' => 'k']))->isEnabled());
    }

    public function test_render_returns_empty_when_disabled(): void
    {
        $this->assertSame('', (new TurnstileCaptchaDriver([]))->render());
    }

    public function test_render_injects_script_only_once_per_cycle(): void
    {
        $first = $this->driver->render();
        $second = $this->driver->render();

        $this->assertStringContainsString('api.js', $first);
        $this->assertStringNotContainsString('api.js', $second);
        $this->assertStringContainsString('site-key', $second);
    }

    public function test_reset_state_restores_script_injection(): void
    {
        $this->driver->render();

        TurnstileCaptchaDriver::resetState();

        $this->assertStringContainsString('api.js', $this->driver->render());
    }

    public function test_verify_throws_when_token_missing(): void
    {
        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage('token is missing');

        $this->driver->verify(['request' => []]);
    }

    public function test_verify_throws_when_secret_missing(): void
    {
        $driver = new TurnstileCaptchaDriver(['site_key' => 'k']);

        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage('secret key is not configured');

        $driver->verify(['request' => ['cf-turnstile-response' => 'token']]);
    }

    public function test_verify_returns_true_on_success(): void
    {
        $driver = new FakeTurnstileCaptchaDriver(['site_key' => 'k', 'secret_key' => 's']);
        $driver->fakeResult = ['success' => true];

        $this->assertTrue($driver->verify(['request' => ['cf-turnstile-response' => 'token']]));
    }

    public function test_verify_throws_on_failed_verification(): void
    {
        $driver = new FakeTurnstileCaptchaDriver(['site_key' => 'k', 'secret_key' => 's']);
        $driver->fakeResult = ['success' => false];

        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage('verification failed');

        $driver->verify(['request' => ['cf-turnstile-response' => 'token']]);
    }
}

final class FakeTurnstileCaptchaDriver extends TurnstileCaptchaDriver
{
    /** @var array<string, mixed> */
    public array $fakeResult = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sendVerificationRequest(string $url, array $data): array
    {
        return $this->fakeResult;
    }
}
