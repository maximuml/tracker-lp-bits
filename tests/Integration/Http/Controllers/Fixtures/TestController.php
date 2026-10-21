<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Controllers\Fixtures;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class TestController extends Controller
{
    /** @return array<int|string, mixed> */
    public function pagination(Request $request): array
    {
        return $this->getPaginationParameters($request);
    }

    public function extraField(Request $request, string $field): bool
    {
        return $this->hasExtraField($request, $field);
    }

    /**
     * @param  array<int|string, mixed>  $additional
     * @param  array<int|string, mixed>  $names
     */
    public function extraSettings(Request $request, array &$additional, array $names): void
    {
        $this->appendExtraSettings($request, $additional, $names);
    }
}
