<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Repositories\RefererHitRepository;
use App\Support\Config\SiteConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts external referers per domain per day (aggregated — no per-request
 * rows, no query strings kept). Internal navigation, tracker endpoints and
 * asset/API/admin paths are ignored. A tracking failure must never break a
 * page, so the write is wrapped.
 */
class TrackReferer
{
    public function __construct(
        private readonly RefererHitRepository $refererHitRepository,
    ) {}

    /** @var list<string> */
    private const EXCLUDED_PATTERNS = [
        'announce*',
        'scrape*',
        'api/*',
        'nexusphp*',
        'livewire*',
        'web/*',
        'horizon*',
        'metrics',
        'csp-report',
        'pic',
        'pic/*',
        'js',
        'js/*',
        'styles',
        'styles/*',
        'images',
        'images/*',
        'themes',
        'themes/*',
        'favicon.ico',
        'robots.txt',
        'opensearch*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET')) {
            return $response;
        }
        foreach (self::EXCLUDED_PATTERNS as $pattern) {
            if ($request->is($pattern)) {
                return $response;
            }
        }

        $host = self::refererHost($request);
        if ($host === null || self::isSelfHost($host, $request)) {
            return $response;
        }

        try {
            $this->record($host, $request->path());
        } catch (Throwable $e) {
            report($e);
        }

        return $response;
    }

    public static function refererHost(Request $request): ?string
    {
        $referer = (string) $request->headers->get('referer', '');
        if ($referer === '') {
            return null;
        }
        $parts = parse_url($referer);
        if (! is_array($parts) || empty($parts['host']) || ! is_string($parts['host'])) {
            return null;
        }
        $host = strtolower($parts['host']);
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host !== '' ? $host : null;
    }

    private static function isSelfHost(string $host, Request $request): bool
    {
        $self = [strtolower($request->getHost())];
        $baseUrl = SiteConfig::current()->basic->baseUrl();
        if ($baseUrl !== '') {
            $baseHost = parse_url(
                str_contains($baseUrl, '://') ? $baseUrl : 'https://'.$baseUrl,
                PHP_URL_HOST
            );
            if (is_string($baseHost) && $baseHost !== '') {
                $self[] = strtolower($baseHost);
            }
        }
        foreach ($self as $own) {
            $own = str_starts_with($own, 'www.') ? substr($own, 4) : $own;
            if ($host === $own || str_ends_with($host, '.'.$own)) {
                return true;
            }
        }

        return false;
    }

    private function record(string $host, string $path): void
    {
        $this->refererHitRepository->recordHit($host, $path);
    }
}
