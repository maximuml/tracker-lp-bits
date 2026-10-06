<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REST rename of a legacy single-purpose POST URI — fields stay
 * permissive so the controller's legacy abort pages remain
 * byte-identical.
 */
class MakePollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pollid' => 'nullable|integer',
            'question' => 'nullable|string|max:255',
            'returnto' => 'nullable|string|max:255',
            'option0' => 'nullable|string|max:255',
            'option1' => 'nullable|string|max:255',
            'option2' => 'nullable|string|max:255',
            'option3' => 'nullable|string|max:255',
            'option4' => 'nullable|string|max:255',
            'option5' => 'nullable|string|max:255',
            'option6' => 'nullable|string|max:255',
            'option7' => 'nullable|string|max:255',
            'option8' => 'nullable|string|max:255',
            'option9' => 'nullable|string|max:255',
            'option10' => 'nullable|string|max:255',
            'option11' => 'nullable|string|max:255',
            'option12' => 'nullable|string|max:255',
            'option13' => 'nullable|string|max:255',
            'option14' => 'nullable|string|max:255',
            'option15' => 'nullable|string|max:255',
            'option16' => 'nullable|string|max:255',
            'option17' => 'nullable|string|max:255',
            'option18' => 'nullable|string|max:255',
            'option19' => 'nullable|string|max:255',
            'sort' => 'nullable',
            'choices' => 'nullable|integer',
        ];
    }
}
