<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/staffmess/send — the REST rename of POST /takestaffmess
 * (staff broadcast PM to selected user classes).
 */
class SendStaffMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sender' => 'nullable|string|in:self,system',
            'subject' => 'nullable|string|max:255',
            'msg' => 'required|string',
            'classes' => 'required|array|min:1',
            'classes.*' => 'integer|min:0',
            'receiver' => 'nullable|integer|min:0',
            'returnto' => 'nullable|string|max:500',
            'dry_run' => 'nullable|boolean',
            'idempotency_key' => 'nullable|string|max:64',
        ];
    }
}
