<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\RequirePasswordChange;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
final class RequirePasswordChangeTest extends TestCase
{
    private function handleRequest(Request $request, ?User $user): Response
    {
        if ($user !== null) {
            Auth::guard('nexus-web')->setUser($user);
        }

        return (new RequirePasswordChange)->handle($request, fn () => new Response('ok'));
    }

    private function flaggedUser(): User
    {
        $user = new User;
        $user->must_change_password = true;

        return $user;
    }

    public function test_flagged_user_get_is_redirected_to_usercp(): void
    {
        $response = $this->handleRequest(Request::create('/forums', 'GET'), $this->flaggedUser());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('action=security', (string) $response->headers->get('Location'));
    }

    public function test_flagged_user_reaches_usercp(): void
    {
        foreach (['/usercp', '/usercp.php', '/usercp.php?action=security'] as $path) {
            $response = $this->handleRequest(Request::create($path, 'GET'), $this->flaggedUser());
            $this->assertSame(200, $response->getStatusCode(), $path);
        }
    }

    public function test_flagged_user_can_logout(): void
    {
        $response = $this->handleRequest(Request::create('/logout', 'POST'), $this->flaggedUser());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_flagged_user_tracker_and_api_untouched(): void
    {
        $response = $this->handleRequest(Request::create('/announce', 'GET'), $this->flaggedUser());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_flagged_user_json_request_gets_403(): void
    {
        $request = Request::create('/forums', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $this->handleRequest($request, $this->flaggedUser());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('password_change_required', (string) $response->getContent());
    }

    public function test_unflagged_user_passes(): void
    {
        $user = new User;
        $user->must_change_password = false;

        $response = $this->handleRequest(Request::create('/forums', 'GET'), $user);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_guest_passes(): void
    {
        $response = $this->handleRequest(Request::create('/forums', 'GET'), null);
        $this->assertSame(200, $response->getStatusCode());
    }
}
