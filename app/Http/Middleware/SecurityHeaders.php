<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add baseline security headers to every web response.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Generate a per-request CSP nonce for inline scripts/styles.
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        View::share('cspNonce', $nonce);

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Filament/Livewire admin panel: Livewire 4 + Alpine 3 support
        // nonce-based CSP. We use the per-request nonce for script-src and
        // style-src instead of 'unsafe-inline'/'unsafe-eval'. Livewire
        // automatically adds the nonce to its injected scripts when the
        // CSP nonce is shared via config.
        //
        // Legacy pages embed third-party widgets (FullCalendar 5,
        // layer.js, domTT) that set element.style.cssText dynamically
        // and inject <style> tags via JS. CSP cannot hash style
        // attributes, so legacy routes use 'unsafe-inline' for style-src
        // (without a nonce — per CSP spec, 'unsafe-inline' is ignored
        // when a nonce is also present). script-src stays nonce-strict.
        if ($this->isFilamentRoute($request)) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-{$nonce}' https://challenges.cloudflare.com; style-src 'self' 'nonce-{$nonce}'; img-src 'self' data: blob: https:; connect-src 'self' https://challenges.cloudflare.com; font-src 'self' data:; frame-ancestors 'self'; form-action 'self' https://www.paypal.com https://www.alipay.com; base-uri 'self'; object-src 'none';");
        } else {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-{$nonce}' https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; connect-src 'self' https://challenges.cloudflare.com; font-src 'self' data:; frame-ancestors 'self'; form-action 'self' https://www.paypal.com https://www.alipay.com; base-uri 'self'; object-src 'none';");
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Check if the current request targets a Filament admin panel route.
     * Filament routes are prefixed with the panel path (default: "nexusphp")
     * and also include Livewire endpoints used by the SPA.
     */
    private function isFilamentRoute(Request $request): bool
    {
        $path = $request->path();

        // Filament panel routes
        if (str_starts_with($path, 'nexusphp') || str_starts_with($path, 'livewire')) {
            return true;
        }

        // API routes use Sanctum, not affected by browser CSP
        return false;
    }
}
