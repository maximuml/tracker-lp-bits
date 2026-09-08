<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-03: Validation for legacy POST /deletemessage.
 */
class DeleteMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => 'required|integer|min:1',
            'type' => 'required|string|in:in,out',
        ];
    }
}
