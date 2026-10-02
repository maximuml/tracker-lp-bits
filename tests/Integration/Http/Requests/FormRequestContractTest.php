<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Requests;

use App\Http\Requests\TagUpdateRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class FormRequestContractTest extends TestCase
{
    /**
     * @return iterable<string, array{0: class-string<FormRequest>}>
     */
    public static function requestClasses(): iterable
    {
        $root = dirname(__DIR__, 4).'/app/Http/Requests';
        $files = array_merge(
            glob($root.'/*.php') ?: [],
            glob($root.'/*/*.php') ?: [],
        );
        sort($files);

        foreach ($files as $file) {
            $relative = substr($file, strlen($root) + 1, -4);
            $class = 'App\\Http\\Requests\\'.str_replace('/', '\\', $relative);
            if (
                class_exists($class)
                && is_subclass_of($class, FormRequest::class)
                && (new ReflectionClass($class))->isInstantiable()
            ) {
                yield $class => [$class];
            }
        }
    }

    #[DataProvider('requestClasses')]
    public function test_request_contract(string $class): void
    {
        $request = new $class;

        if ($request instanceof TagUpdateRequest) {
            $route = app('router')->get('/__probe/{id}', static fn () => null);
            $route->bind(Request::create('/__probe/1'));
            $request->setRouteResolver(static fn () => $route);
        }

        $this->assertIsArray($request->rules());
        $this->assertIsBool($request->authorize());

        if (method_exists($request, 'messages')) {
            $this->assertIsArray($request->messages());
        }
        if (method_exists($request, 'attributes')) {
            $this->assertIsArray($request->attributes());
        }
    }
}
