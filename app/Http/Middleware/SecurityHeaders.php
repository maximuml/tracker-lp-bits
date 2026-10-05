<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
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
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        if ($this->isRecoveryLinkRequest($request)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Referrer-Policy', 'no-referrer');
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        } elseif (! $response->headers->has('Referrer-Policy')) {
            $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        }
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Filament/Livewire admin panel injects inline styles dynamically via
        // JavaScript (element.style, <style> tags, Alpine x-bind:style).
        // Per CSP spec, 'unsafe-inline' is ignored when a nonce is present in
        // style-src, so we use 'unsafe-inline' (no nonce) for Filament routes.
        // Legacy routes keep the nonce-strict style-src to preserve the
        // existing visual behavior — inline style attributes on legacy pages
        // (e.g. <span style="color:#aaaaaa">) were already blocked by the
        // nonce-only policy, and allowing them would cause color-contrast
        // regressions detected by axe-core.
        // Same for script-src: Livewire 3 / Alpine evaluate expressions with
        // new Function() (unsafe-eval) and Filament ships inline boot scripts —
        // a nonce-strict script-src leaves the whole panel dead (login button
        // does nothing). Filament routes get the pragmatic admin policy.
        $isFilament = $this->isRelaxedPolicyRoute($request);
        // Legacy pages keep <style> elements nonce-locked (style-src-elem),
        // but allow style attributes + CSSOM writes (style-src-attr
        // 'unsafe-inline'): legacy JS toggles visibility via element.style,
        // and a strict policy silently breaks show/hide controls. Style
        // attributes cannot execute script — script-src stays nonce-strict.
        $styleSrc = $isFilament
            ? "style-src 'self' 'unsafe-inline'"
            : "style-src 'self' 'nonce-{$nonce}'; style-src-elem 'self' 'nonce-{$nonce}'; style-src-attr 'unsafe-inline'";
        $scriptSrc = $isFilament
            ? "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://challenges.cloudflare.com"
            : "script-src 'self' 'nonce-{$nonce}' https://challenges.cloudflare.com";

        // Violation reporting: report-uri covers Firefox/legacy agents,
        // report-to + Reporting-Endpoints covers the Reporting API (Chrome).
        $reportUri = '/csp-report';
        $response->headers->set('Reporting-Endpoints', 'csp-endpoint="'.$reportUri.'"');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; {$scriptSrc}; {$styleSrc}; img-src 'self' data: blob: https:; connect-src 'self' https://challenges.cloudflare.com; font-src 'self' data:; frame-ancestors 'self'; form-action 'self' https://www.paypal.com; base-uri 'self'; object-src 'none'; report-uri {$reportUri}; report-to csp-endpoint;");

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function isRecoveryLinkRequest(Request $request): bool
    {
        return $request->is('recover/reset')
            || ($request->is('recover') && $request->query->has('secret'));
    }

    /**
     * Check if the current request targets a route that needs the relaxed
     * (unsafe-inline/unsafe-eval) CSP: Filament admin routes are prefixed
     * with the panel path (default: "nexusphp") plus Livewire endpoints,
     * and the Horizon dashboard ships its own inline boot script
     * (window.Horizon = {...}) that a nonce-strict policy blocks. Both are
     * staff-gated surfaces.
     */
    private function isRelaxedPolicyRoute(Request $request): bool
    {
        $path = $request->path();

        // Filament panel + Livewire + Horizon dashboard routes
        if (str_starts_with($path, 'nexusphp') || str_starts_with($path, 'livewire') || str_starts_with($path, 'horizon')) {
            return true;
        }

        // API routes use Sanctum, not affected by browser CSP
        return false;
    }
}
