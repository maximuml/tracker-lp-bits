<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/admin/users/self-enable — the REST rename of POST /self-enable
 * (staff/tool endpoint). Fields stay permissive: the controller keeps
 * its legacy abort pages byte-identical.
 */
class SelfEnableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'submit' => 'nullable|string',
            'username' => 'nullable|string|max:255',
            'id' => 'nullable|integer',

        ];
    }
}
