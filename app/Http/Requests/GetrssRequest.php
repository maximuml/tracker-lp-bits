<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REST rename of a legacy single-purpose POST URI — fields stay
 * permissive so the controller's legacy abort pages remain
 * byte-identical.
 */
class GetrssRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'item' => 'nullable|string|max:255',
            'cats' => 'nullable|array',
            'cats.*' => 'nullable',
            'type' => 'nullable|string|max:64',
            'search_mode' => 'nullable|string|max:64',
        ];
    }
}
