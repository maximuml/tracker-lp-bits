<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REST rename of a legacy single-purpose POST URI — fields stay
 * permissive so the controller's legacy abort pages remain
 * byte-identical.
 */
class ModrulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'act' => 'nullable|string|max:32',
            'id' => 'nullable|integer',
            'title' => 'nullable|string|max:255',
            'text' => 'nullable|string',
            'language' => 'nullable|integer',
        ];
    }
}
