<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/messages/delete/{type} — the REST rename of POST /deletemessage.
 * The mailbox side travels in the URI instead of a body field, so the
 * route parameter is merged in for validation.
 */
class DeleteTypedMessageRequest extends FormRequest
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

    /** @return array<string, mixed> */
    public function validationData(): array
    {
        return array_merge($this->all(), ['type' => $this->route('type')]);
    }
}
