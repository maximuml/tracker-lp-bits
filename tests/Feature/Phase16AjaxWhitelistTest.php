<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AjaxService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 1.6: verify that the /ajax endpoint only dispatches actions
 * that are explicitly listed in AjaxService::ALLOWED_ACTIONS.
 */
final class Phase16AjaxWhitelistTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]);
    }

    private function csrfToken(): string
    {
        return Str::random(40);
    }

    public function test_whitelisted_action_passes_the_gate(): void
    {
        // getToastNotifications is in the whitelist and requires login
        $user = User::factory()->create();
        $token = $this->csrfToken();
        $response = $this->withNexusCookie($user)
            ->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->post('/ajax', ['action' => 'getToastNotifications', '_token' => $token]);

        // Should not be a "hacking attempt" rejection (which returns 200
        // with ret=1). The key assertion is that the action is dispatched,
        // not blocked by the whitelist check.
        $response->assertOk();
        $response->assertJsonStructure(['ret']);
        // ret=0 means success, ret=1 means error — but NOT "Invalid action"
        $body = $response->json();
        $this->assertNotEquals('Invalid action: getToastNotifications', $body['msg'] ?? '', 'Whitelisted action should not be rejected.');
    }

    public function test_non_whitelisted_action_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->csrfToken();
        $response = $this->withNexusCookie($user)
            ->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->post('/ajax', ['action' => '__construct', '_token' => $token]);

        $response->assertOk();
        $response->assertJsonPath('ret', 1);
        $body = $response->json();
        $this->assertStringContainsString('Invalid action', (string) ($body['msg'] ?? ''), 'Non-whitelisted action should be rejected.');
    }

    public function test_unknown_action_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->csrfToken();
        $response = $this->withNexusCookie($user)
            ->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->post('/ajax', ['action' => 'doesNotExist', '_token' => $token]);

        $response->assertOk();
        $response->assertJsonPath('ret', 1);
        $body = $response->json();
        $this->assertStringContainsString('Invalid action', (string) ($body['msg'] ?? ''), 'Unknown action should be rejected.');
    }

    public function test_empty_action_is_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->csrfToken();
        $response = $this->withNexusCookie($user)
            ->withSession(['_token' => $token])
            ->withHeader('X-CSRF-TOKEN', $token)
            ->post('/ajax', ['action' => '', '_token' => $token]);

        $response->assertOk();
        $response->assertJsonPath('ret', 1);
    }

    public function test_allowed_actions_constant_lists_all_public_methods(): void
    {
        // Verify that every public method on AjaxService is in the
        // whitelist — no method should be silently exposed without explicit
        // registration.
        $reflection = new \ReflectionClass(AjaxService::class);
        $publicMethods = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() === AjaxService::class
                && $method->getName() !== '__construct'
            ) {
                $publicMethods[] = $method->getName();
            }
        }

        $allowed = AjaxService::ALLOWED_ACTIONS;

        foreach ($publicMethods as $method) {
            $this->assertContains($method, $allowed, "Public method '{$method}' is not in AjaxService::ALLOWED_ACTIONS — add it or make it non-public.");
        }
    }
}
