<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Url;
use Filament\Facades\Filament as FilamentFacade;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Request;

class Filament extends Authenticate
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  mixed  $request
     */
    protected function redirectTo($request): ?string
    {
        return Url::schemeAndHost(false).'/login';
    }

    /**
     * @param  array<int, string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        if ($this->isGuestPageLivewireRequest($request)) {
            return;
        }

        parent::authenticate($request, $guards);
    }

    /**
     * The panel's Livewire update route carries this middleware globally, so
     * guest-only pages (the login screen) can never call their components.
     * Exempt updates whose originating path is the panel login URL — the
     * snapshot checksum binds the component, so the path cannot be forged to
     * reach another component.
     */
    private function isGuestPageLivewireRequest(Request $request): bool
    {
        $snapshot = json_decode((string) $request->input('components.0.snapshot', ''), true);
        $path = is_array($snapshot) ? ($snapshot['memo']['path'] ?? null) : null;

        if (! is_string($path) || $path === '') {
            return false;
        }

        $loginPath = parse_url(FilamentFacade::getLoginUrl() ?? '', PHP_URL_PATH);

        return is_string($loginPath) && trim($path, '/') === trim($loginPath, '/');
    }
}
