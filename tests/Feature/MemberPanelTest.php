<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Member\Pages\MyPasskeys;
use App\Models\Passkey;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stage 6.1 prototype: /my member panel (Filament) access + data scoping.
 */
final class MemberPanelTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/my/passkeys');

        $response->assertRedirectContains('/login.php');
    }

    public function test_authenticated_user_can_open_passkeys_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'nexus-web')->get('/my/passkeys');

        $response->assertOk();
    }

    public function test_passkeys_table_shows_only_own_records(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        // Table rows hydrate through Livewire, not the first page render.

        Passkey::query()->create([
            'user_id' => $owner->id,
            'aaguid' => '00000000000000000000000000000000',
            'credential_id' => 'owner-cred-aa11',
            'public_key' => 'pk',
            'counter' => 0,
        ]);
        Passkey::query()->create([
            'user_id' => $other->id,
            'aaguid' => '00000000000000000000000000000000',
            'credential_id' => 'other-cred-bb22',
            'public_key' => 'pk',
            'counter' => 0,
        ]);

        $this->actingAs($owner, 'nexus-web');
        Auth::shouldUse('nexus-web');
        Filament::setCurrentPanel('member');

        Livewire::test(MyPasskeys::class)
            ->assertSee('owner-cred-aa11')
            ->assertDontSee('other-cred-bb22');
    }

    public function test_member_routes_get_relaxed_filament_csp(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'nexus-web')->get('/my/passkeys');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("'unsafe-inline' 'unsafe-eval'", $csp);
    }

    public function test_legacy_my_prefixed_pages_keep_strict_csp(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'nexus-web')->get('/mybonus');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('nonce-', $csp);
        $this->assertStringNotContainsString('unsafe-eval', $csp);
    }
}
