<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/admin/users/reset — the REST rename of POST /reset
 * (staff/tool endpoint). Fields stay permissive: the controller keeps
 * its legacy abort pages byte-identical.
 */
class ResetUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'username' => 'nullable|string|max:255',
            'newpassword' => 'nullable|string|max:255',
            'newpasswordagain' => 'nullable|string|max:255',

        ];
    }
}
