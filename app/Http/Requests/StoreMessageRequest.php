<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-03: Validation for legacy POST /takemessage.
 */
class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'receiver' => 'required_without:forward|integer|min:1',
            'origmsg' => 'nullable|integer|min:0',
            'body' => 'required|string',
            'subject' => 'nullable|string|max:255',
            'forward' => 'nullable|string|in:1',
            'to' => 'required_with:forward|string',
            'save' => 'nullable|string|in:yes',
            'returnto' => 'nullable|string|max:500',
            'delete' => 'nullable|string|in:yes',
        ];
    }
}
