<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/system/mail-test — the REST rename of POST /mailtest send
 * (staff/tool endpoint). Fields stay permissive: the controller keeps
 * its legacy abort pages byte-identical.
 */
class MailTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'nullable|string',
            'email' => 'nullable|string|max:255',

        ];
    }
}
