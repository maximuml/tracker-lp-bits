<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * W1-01: Route inventory command.
 *
 * Generates a structured report of all registered routes with columns:
 * URI, methods, controller action, middleware, auth guard, read/mutation,
 * and whether the route has a FormRequest.
 *
 * Usage:
 *   php artisan route:inventory
 *   php artisan route:inventory --output=docs/route-inventory.md
 *   php artisan route:inventory --format=json --output=route-inventory.json
 */
final class RouteInventoryCommand extends Command
{
    protected $signature = 'route:inventory
                            {--format=md : Output format (md, json, csv)}
                            {--output= : Output file path (defaults to stdout)}';

    protected $description = 'Generate a structured route inventory report (W1-01)';

    public function handle(): int
    {
        $routes = RouteFacade::getRoutes()->getRoutes();
        $format = (string) $this->option('format');
        $output = $this->option('output');

        $rows = [];
        foreach ($routes as $route) {
            $rows[] = $this->buildRow($route);
        }

        // Sort by URI then method for stable output
        usort($rows, fn ($a, $b) => strcmp($a['uri'], $b['uri']) ?: strcmp($a['methods'], $b['methods']));

        $content = match ($format) {
            'json' => $this->formatJson($rows),
            'csv' => $this->formatCsv($rows),
            default => $this->formatMarkdown($rows),
        };

        if ($output !== null && $output !== '') {
            file_put_contents($output, $content);
            $this->info("Route inventory written to {$output} (".count($rows).' routes)');
        } else {
            $this->line($content);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, string>
     */
    private function buildRow(Route $route): array
    {
        $methods = array_filter($route->methods(), fn ($m) => $m !== 'OPTIONS' && $m !== 'HEAD');
        $methodsStr = implode('|', $methods);
        $action = $route->getActionName();
        $uri = $route->uri();
        $middleware = implode(', ', $route->gatherMiddleware());
        $guard = $this->detectGuard($middleware);
        $isMutation = $this->isMutation($methods);
        $hasFormRequest = $this->hasFormRequest($route) ? 'yes' : 'no';
        $domain = $route->getDomain() ?? '';

        return [
            'uri' => $uri,
            'methods' => $methodsStr,
            'action' => $action,
            'middleware' => $middleware,
            'guard' => $guard,
            'type' => $isMutation ? 'mutation' : 'read',
            'form_request' => $hasFormRequest,
            'domain' => $domain,
        ];
    }

    private function detectGuard(string $middleware): string
    {
        if (str_contains($middleware, 'auth.nexus')) {
            return 'nexus-web';
        }
        if (str_contains($middleware, 'auth:sanctum')) {
            return 'sanctum';
        }
        if (str_contains($middleware, 'auth:')) {
            return 'auth';
        }

        return 'none';
    }

    /**
     * @param  array<int, string>  $methods
     */
    private function isMutation(array $methods): bool
    {
        $mutationMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

        foreach ($methods as $method) {
            if (in_array($method, $mutationMethods, true)) {
                return true;
            }
        }

        return false;
    }

    private function hasFormRequest(Route $route): bool
    {
        $action = $route->getAction();

        if (! isset($action['controller'])) {
            return false;
        }

        $controllerAction = $action['controller'];
        if (! str_contains($controllerAction, '@')) {
            return false;
        }

        [$controllerClass, $methodName] = explode('@', $controllerAction, 2);

        if (! class_exists($controllerClass)) {
            return false;
        }

        try {
            $reflection = new \ReflectionMethod($controllerClass, $methodName);
        } catch (\ReflectionException) {
            return false;
        }

        foreach ($reflection->getParameters() as $param) {
            $type = $param->getType();
            if ($type === null) {
                continue;
            }
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : '';
            if ($typeName !== '' && class_exists($typeName)) {
                if (is_subclass_of($typeName, FormRequest::class)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function formatMarkdown(array $rows): string
    {
        $lines = [
            '# Route Inventory',
            '',
            'Generated by `php artisan route:inventory` (W1-01).',
            '',
            'Total routes: '.count($rows),
            '',
            '| URI | Methods | Action | Guard | Type | FormRequest | Middleware |',
            '|-----|---------|--------|-------|------|-------------|------------|',
        ];

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '| %s | %s | %s | %s | %s | %s | %s |',
                $row['uri'],
                $row['methods'],
                $row['action'],
                $row['guard'],
                $row['type'],
                $row['form_request'],
                $row['middleware'],
            );
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function formatJson(array $rows): string
    {
        return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function formatCsv(array $rows): string
    {
        $headers = ['uri', 'methods', 'action', 'middleware', 'guard', 'type', 'form_request', 'domain'];
        $lines = [implode(',', $headers)];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(
                fn ($v) => '"'.str_replace('"', '""', $v).'"',
                array_map(fn ($h) => $row[$h], $headers),
            ));
        }

        return implode("\n", $lines)."\n";
    }
}
