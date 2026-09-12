<?php

declare(strict_types=1);

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Label + input pair with associated error/help text.
 *
 * Renders the `for`/`id` link, `required`, `aria-invalid` and
 * `aria-describedby` wiring so migrated forms stay accessible without
 * hand-written attribute bookkeeping.
 */
final class FormField extends Component
{
    public readonly string $fieldId;

    /** @var list<string> */
    public readonly array $describedBy;

    public readonly bool $hasError;

    public readonly bool $hasHelp;

    public function __construct(
        public readonly string $label,
        public readonly string $name,
        public readonly ?string $id = null,
        public readonly string $type = 'text',
        public readonly ?string $value = null,
        public readonly bool $required = false,
        public readonly ?string $error = null,
        public readonly ?string $help = null,
    ) {
        $this->fieldId = $id ?? $name;
        $this->hasError = $error !== null && $error !== '';
        $this->hasHelp = $help !== null && $help !== '';

        $describedBy = [];
        if ($this->hasError) {
            $describedBy[] = $this->fieldId.'-error';
        }
        if ($this->hasHelp) {
            $describedBy[] = $this->fieldId.'-help';
        }
        $this->describedBy = $describedBy;
    }

    public function render(): View
    {
        return view('components.form-field');
    }
}
