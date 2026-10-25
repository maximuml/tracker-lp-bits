<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Captcha\Drivers;

use App\Services\Captcha\Drivers\RecaptchaV2CaptchaDriver;
use App\Services\Captcha\Exceptions\CaptchaValidationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class RecaptchaV2CaptchaDriverTest extends TestCase
{
    private RecaptchaV2CaptchaDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new RecaptchaV2CaptchaDriver([
            'site_key' => 'site-key',
            'secret_key' => 'secret-key',
        ]);
    }

    public function test_verify_posts_form_to_recaptcha_siteverify(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->assertTrue($this->driver->verify(
            ['request' => ['g-recaptcha-response' => 'tok']],
            ['ip' => '1.2.3.4'],
        ));

        Http::assertSent(fn (Request $r) => $r->url() === 'https://www.recaptcha.net/recaptcha/api/siteverify'
            && $r['secret'] === 'secret-key'
            && $r['response'] === 'tok'
            && $r['remoteip'] === '1.2.3.4');
        Http::assertSentCount(1);
    }

    public function test_verify_omits_remoteip_without_context_ip(): void
    {
        Http::fake(['*' => Http::response(['success' => true])]);

        $this->assertTrue($this->driver->verify(['request' => ['g-recaptcha-response' => 'tok']]));

        Http::assertSent(fn (Request $r) => ! isset($r['remoteip']));
    }

    public function test_verify_throws_on_provider_rejection(): void
    {
        Http::fake(['*' => Http::response(['success' => false])]);

        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage('verification failed');

        $this->driver->verify(['request' => ['g-recaptcha-response' => 'tok']]);
    }

    public function test_verify_throws_on_malformed_response(): void
    {
        Http::fake(['*' => Http::response('<html>error</html>', 200)]);

        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage('Unexpected captcha verification response');

        $this->driver->verify(['request' => ['g-recaptcha-response' => 'tok']]);
    }

    public function test_verify_throws_on_connection_failure(): void
    {
        Http::fake(fn () => throw new ConnectionException('timed out'));

        $this->expectException(CaptchaValidationException::class);
        $this->expectExceptionMessage('request failed');

        $this->driver->verify(['request' => ['g-recaptcha-response' => 'tok']]);
    }
}
