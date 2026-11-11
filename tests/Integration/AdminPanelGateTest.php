<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\UserClass;
use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class AdminPanelGateTest extends TestCase
{
    use DatabaseTransactions;

    private function fakeLivewirePanelRequest(): void
    {
        $snapshot = (string) json_encode([
            'memo' => ['name' => 'App\\Filament\\Resources\\UserResource\\Pages\\ListUsers'],
            'data' => [],
        ]);
        $request = Request::create('/livewire/update', 'POST', [
            'components' => [['snapshot' => $snapshot]],
        ]);
        $this->app->instance('request', $request);
    }

    private function fakeWebRequest(): void
    {
        $this->app->instance('request', Request::create('/web/index', 'GET'));
    }

    public function test_non_admin_cannot_authorize_policy_less_model_in_panel_context(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->fakeLivewirePanelRequest();

        $this->assertFalse(Gate::forUser($user)->allows('view', new Bookmark));
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', Bookmark::class));
    }

    public function test_policy_less_model_outside_panel_context_uses_default_deny(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $this->fakeWebRequest();

        $this->assertFalse(Gate::forUser($user)->allows('view', new Bookmark));
    }

    public function test_model_with_policy_is_not_intercepted_in_panel_context(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $other = User::factory()->create(['class' => UserClass::USER->value]);
        $this->fakeLivewirePanelRequest();

        // UserPolicy::view allows viewing a normal-privacy user — proves the
        // Gate::before fallback returned null and the real policy ran.
        $this->assertTrue(Gate::forUser($user)->allows('view', $other));
    }

    public function test_admin_panel_context_via_route_name(): void
    {
        $user = User::factory()->create(['class' => UserClass::USER->value]);
        $route = new Route('GET', '/filament/admin/users', []);
        $route->name('filament.admin.users.index');
        $request = Request::create('/filament/admin/users', 'GET');
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);

        $this->assertFalse(Gate::forUser($user)->allows('view', new Bookmark));
    }
}
