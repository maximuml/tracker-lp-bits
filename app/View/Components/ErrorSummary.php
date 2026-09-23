<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Contracts\View\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\Component;

/**
 * role="alert" summary listing every validation error of a form.
 * Items are strings or ['message' => ..., 'href' => '#field-id'] pairs —
 * the href variant links each error to the offending input.
 */
final class ErrorSummary extends Component
{
    /** @var list<array{message: string, href: string|null}> */
    public readonly array $items;

    /**
     * @param  MessageBag|ViewErrorBag|array<int, string|array{message?: string, href?: string}>  $errors
     */
    public function __construct(
        public readonly string $title = '',
        array|MessageBag|ViewErrorBag $errors = [],
    ) {
        if ($errors instanceof ViewErrorBag) {
            $errors = $errors->getBag('default');
        }
        $messages = $errors instanceof MessageBag ? $errors->all() : $errors;
        $items = [];
        foreach ($messages as $message) {
            $items[] = is_array($message)
                ? ['message' => (string) ($message['message'] ?? ''), 'href' => $message['href'] ?? null]
                : ['message' => (string) $message, 'href' => null];
        }
        $this->items = $items;
    }

    public function shouldRender(): bool
    {
        return $this->items !== [];
    }

    public function render(): View
    {
        return view('components.error-summary');
    }
}
