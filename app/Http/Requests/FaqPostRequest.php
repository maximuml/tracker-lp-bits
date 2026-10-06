<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REST rename of a legacy single-purpose POST URI — fields stay
 * permissive so the controller's legacy abort pages remain
 * byte-identical.
 */
class FaqPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'nullable|string|max:64',
            'id' => 'nullable|integer',
            'order' => 'nullable',
            'question' => 'nullable|string',
            'answer' => 'nullable|string',
            'flag' => 'nullable|integer',
            'categ' => 'nullable|integer',
        ];
    }
}
