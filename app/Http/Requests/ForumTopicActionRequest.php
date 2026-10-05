<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * W1-04: Validation for legacy POST /forums with action=setlocked|setsticky|hltopic.
 */
class ForumTopicActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'topicid' => 'required|integer|min:1',
            'locked' => 'nullable|boolean',
            'sticky' => 'nullable|string|in:yes,no',
            'color' => 'nullable|integer|min:0',
            'returnto' => 'nullable|string|max:500',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(redirect('/forums'));
    }
}
