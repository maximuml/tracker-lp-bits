<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Fixtures;

use App\Http\Controllers\Controller;

class TestController extends Controller
{
    /** @return array<int|string, mixed> */
    public function pagination(): array
    {
        return $this->getPaginationParameters();
    }

    public function extraField(string $field): bool
    {
        return $this->hasExtraField($field);
    }

    /**
     * @param  array<int|string, mixed>  $additional
     * @param  array<int|string, mixed>  $names
     */
    public function extraSettings(array &$additional, array $names): void
    {
        $this->appendExtraSettings($additional, $names);
    }
}
