<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/system/location — the REST rename of POST /location mutation
 * (staff/tool endpoint). Fields stay permissive: the controller keeps
 * its legacy abort pages byte-identical.
 */
class LocationPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'range_start_ip' => 'nullable|string',
            'range_end_ip' => 'nullable|string',
            'sure' => 'nullable|string',
            'delid' => 'nullable|integer',

        ];
    }
}
