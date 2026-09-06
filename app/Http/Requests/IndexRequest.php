<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request for index/list endpoints with sort + pagination params.
 *
 * Resource-specific index requests may extend this and add filter rules.
 */
abstract class IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return array_merge($this->filterRules(), [
            'sort_field' => 'nullable|string|max:50',
            'sort_type' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
    }

    /**
     * Resource-specific filter rules.
     *
     * @return array<int|string, mixed>
     */
    abstract protected function filterRules(): array;
}
