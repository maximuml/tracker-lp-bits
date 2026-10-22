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
        if ($this->isGuestReachableLivewireRequest($request)) {
            return;
        }

        parent::authenticate($request, $guards);
    }

    /**
     * The panel's Livewire update route carries this middleware globally, so
     * every Livewire component update in the app — public pages included —
     * arrives here. Panel authentication must therefore only be enforced for
     * components that originate from panel pages: exempt updates whose
     * snapshot paths are guest-reachable — the panel login URL or any path
     * outside the panel prefix. The snapshot checksum binds each component's
     * path, so it cannot be forged to reach another component.
     *
     * A batched update may carry several components: every snapshot must be
     * guest-reachable, otherwise the whole request requires authentication.
     * Livewire verifies each snapshot's checksum when the update is
     * processed, so a batch of exempt-path snapshots can only contain
     * genuinely rendered guest-reachable components.
     */
    private function isGuestReachableLivewireRequest(Request $request): bool
    {
        $panelPath = trim((string) (FilamentFacade::getCurrentOrDefaultPanel()?->getPath() ?? ''), '/');
        $loginPath = parse_url(FilamentFacade::getLoginUrl() ?? '', PHP_URL_PATH);
        $loginPath = is_string($loginPath) ? trim($loginPath, '/') : '';

        if ($panelPath === '' || $loginPath === '') {
            return false;
        }

        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            $snapshot = json_decode((string) (is_array($component) ? ($component['snapshot'] ?? '') : ''), true);
            $path = is_array($snapshot) ? ($snapshot['memo']['path'] ?? null) : null;

            if (! is_string($path) || ($path = trim($path, '/')) === '') {
                return false;
            }

            if ($path === $loginPath) {
                continue;
            }

            if ($path === $panelPath || str_starts_with($path, $panelPath.'/')) {
                return false;
            }
        }

        return true;
    }
}
