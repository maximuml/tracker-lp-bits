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

        // Both Filament/Livewire and legacy pages inject inline styles
        // dynamically via JavaScript (element.style.cssText, <style> tags,
        // Alpine.js x-bind:style, FullCalendar 5, layer.js, domTT).
        // Per CSP spec, when a nonce is present in style-src, 'unsafe-inline'
        // is ignored — so we cannot use both. We use 'unsafe-inline' without
        // a nonce for style-src on all routes. script-src stays nonce-strict
        // on all routes, which provides the primary XSS protection.
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self' 'nonce-{$nonce}' https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: https:; connect-src 'self' https://challenges.cloudflare.com; font-src 'self' data:; frame-ancestors 'self'; form-action 'self' https://www.paypal.com https://www.alipay.com; base-uri 'self'; object-src 'none';");

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
