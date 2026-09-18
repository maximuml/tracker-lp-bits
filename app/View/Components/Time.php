<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Support\Html\SafeHtml;
use App\Support\Time as TimeFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Semantic `<time>` display for legacy timestamps.
 *
 * Wraps App\Support\Time::timeParts() so views render the legacy
 * elapsed/absolute time via `{{ }}` without leaking markup — outputting
 * `{{ Time::format(...) }}` escaped the `<span title>` string into
 * visible text.
 *
 *     <x-time :value="$row['added']" />
 *     <x-time :value="$user['added']" :force="true" />
 */
final class Time extends Component
{
    /** @var array{datetime: string, title: string, inner: SafeHtml}|null */
    public readonly ?array $parts;

    public function __construct(
        mixed $value,
        bool $ago = true,
        bool $twoLine = false,
        bool $force = false,
        bool $oneUnit = false,
        bool $future = false,
    ) {
        $this->parts = TimeFormatter::timeParts($value, $ago, $twoLine, $force, $oneUnit, $future);
    }

    public function render(): View
    {
        return view('components.time');
    }
}
