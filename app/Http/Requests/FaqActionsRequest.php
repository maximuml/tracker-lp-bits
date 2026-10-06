<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REST rename of a legacy single-purpose POST URI — fields stay
 * permissive so the controller's legacy abort pages remain
 * byte-identical.
 */
class FaqActionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'nullable|string|max:32',
            'act' => 'nullable|string|max:32',
            'id' => 'nullable|integer',
            'order' => 'nullable',
            'question' => 'nullable|string',
            'answer' => 'nullable|string',
            'title' => 'nullable|string|max:255',
            'flag' => 'nullable|integer',
            'categ' => 'nullable|integer',
            'confirm' => 'nullable|string|max:32',
        ];
    }
}
