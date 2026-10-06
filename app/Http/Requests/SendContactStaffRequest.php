<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/contactstaff/send — the REST rename of POST /takecontact
 * (user-facing "contact staff" message).
 */
class SendContactStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'returnto' => 'nullable|string|max:500',
        ];
    }
}
