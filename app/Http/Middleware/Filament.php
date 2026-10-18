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
     *
     * A batched update may carry several components: every snapshot must
     * originate from the login page, otherwise the whole request requires
     * authentication. Livewire verifies each snapshot's checksum when the
     * update is processed, so a batch of login-path snapshots can only
     * contain genuinely rendered login components.
     */
    private function isGuestPageLivewireRequest(Request $request): bool
    {
        $loginPath = parse_url(FilamentFacade::getLoginUrl() ?? '', PHP_URL_PATH);

        if (! is_string($loginPath) || trim($loginPath, '/') === '') {
            return false;
        }

        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $snapshot = json_decode((string) (is_array($component) ? ($component['snapshot'] ?? '') : ''), true);
            $path = is_array($snapshot) ? ($snapshot['memo']['path'] ?? null) : null;

            if (! is_string($path) || trim($path, '/') !== trim($loginPath, '/')) {
                return false;
            }
        }

        return true;
    }
}
