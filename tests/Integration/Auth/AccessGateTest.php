<?php

namespace Tests\Integration\Auth;

use App\Auth\AccessGate;
use App\Auth\AuthContext;
use App\Contracts\Repositories\AuthRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Pins the registration-check gates in AccessGate: the maxusers / maxip
 * guards are boolean-flagged, so the tests assert the abort (and its
 * text) rather than the boolean branch result.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class AccessGateTest extends TestCase
{
    private AuthRepositoryInterface&MockInterface $authRepository;

    private AccessGate $gate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->authRepository = Mockery::mock(AuthRepositoryInterface::class);
        $this->gate = new AccessGate($this->authRepository);
    }

    /** @param array<string, mixed> $registration */
    private function context(array $registration = []): AuthContext
    {
        return new AuthContext(
            user: null,
            cache: null,
            ip: '10.0.0.9',
            requestUri: '/signup.php',
            requestBody: [],
            queryParams: [],
            request: [],
            cookies: [],
            maxLoginAttempts: 6,
            captchaEnabled: false,
            registration: array_merge([
                'invitesystem' => 'yes',
                'registration' => 'yes',
                'maxusers' => 100,
                'maxip' => 3,
            ], $registration),
            langFolder: 'en',
            moderatorClass: 4,
            script: 'signup',
        );
    }

    private function assertCheckAborts(string $needle, AuthContext $context): void
    {
        try {
            $this->gate->registrationCheck('normal', true, true, $context);
            $this->fail('Expected HttpResponseException');
        } catch (HttpResponseException $e) {
            $this->assertStringContainsString(e($needle), (string) $e->getResponse()->getContent());
        }
    }

    public function test_max_users_limit_aborts_when_reached(): void
    {
        // Flagged-on gate + count above the cap must abort — the mutant
        // negates the flag and skips the check entirely.
        $this->authRepository->shouldReceive('countUsers')->andReturn(150);
        $this->authRepository->shouldReceive('countUsersByIp')->andReturn(0);

        $this->assertCheckAborts(
            'The current user account limit has been reached',
            $this->context(['maxusers' => 100]),
        );
    }

    public function test_max_users_under_limit_continues(): void
    {
        $this->authRepository->shouldReceive('countUsers')->andReturn(50);
        $this->authRepository->shouldReceive('countUsersByIp')->andReturn(0);

        $this->assertTrue($this->gate->registrationCheck('normal', true, true, $this->context()));
    }

    public function test_ip_limit_aborts_when_exceeded(): void
    {
        $this->authRepository->shouldReceive('countUsers')->andReturn(0);
        $this->authRepository->shouldReceive('countUsersByIp')->with('10.0.0.9')->andReturn(5);

        $this->assertCheckAborts(
            'already being used on too many account',
            $this->context(),
        );
    }
}
