<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/system/update — the REST rename of POST /takeupdate
 * (staff bulk-handles reports: mark dealt or delete selected ids).
 */
class SystemUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'delreport' => 'nullable|array',
            'delreport.*' => 'nullable',
            'setdealt' => 'nullable',
            'delete' => 'nullable',
        ];
    }
}
