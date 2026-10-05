<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/admin/user-ban-log — the REST rename of POST /user-ban-log
 * (staff/tool endpoint). Fields stay permissive: the controller keeps
 * its legacy abort pages byte-identical.
 */
class UserBanLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',

        ];
    }
}
