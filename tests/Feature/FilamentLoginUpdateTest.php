<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * The panel's Livewire update route runs the Filament auth middleware
 * globally. Guest-only pages (the login screen) must still be able to call
 * their components — the middleware exempts updates whose snapshot path is
 * the panel login URL — while updates for authenticated pages keep bouncing
 * guests to the legacy login.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class FilamentLoginUpdateTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function livewirePayload(string ...$paths): array
    {
        $components = array_map(
            fn (string $path): array => [
                'snapshot' => json_encode([
                    'data' => [],
                    'memo' => ['id' => 'test', 'name' => 'TestComponent', 'path' => $path],
                    'checksum' => 'invalid-checksum-for-test',
                ]),
                'updates' => [],
                'calls' => [],
            ],
            $paths,
        );

        return ['components' => $components];
    }

    public function test_guest_livewire_update_for_panel_login_page_is_not_auth_redirected(): void
    {
        $response = $this->postJson('/livewire/update', $this->livewirePayload('nexusphp/login'));

        $this->assertNotSame(
            302,
            $response->getStatusCode(),
            'Login-page Livewire update must not bounce to the legacy login redirect.',
        );
    }

    public function test_guest_livewire_update_for_authed_panel_page_is_unauthenticated(): void
    {
        $response = $this->postJson('/livewire/update', $this->livewirePayload('nexusphp'));

        $response->assertUnauthorized();
    }

    /**
     * Regression: the guest exemption previously inspected only
     * components.0 — a batch could prepend a login-path snapshot and
     * smuggle an authenticated-page component past the auth middleware.
     */
    public function test_guest_livewire_update_batch_with_authed_component_is_unauthenticated(): void
    {
        $response = $this->postJson('/livewire/update', $this->livewirePayload('nexusphp/login', 'nexusphp'));

        $response->assertUnauthorized();
    }

    public function test_guest_livewire_update_batch_of_only_login_components_is_not_auth_redirected(): void
    {
        $response = $this->postJson('/livewire/update', $this->livewirePayload('nexusphp/login', 'nexusphp/login'));

        $this->assertNotSame(
            302,
            $response->getStatusCode(),
            'Login-page-only Livewire batches must not bounce to the legacy login redirect.',
        );
    }
}
