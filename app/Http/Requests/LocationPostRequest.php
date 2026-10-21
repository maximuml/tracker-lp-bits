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
            'check_range' => 'nullable|string',
            'sure' => 'nullable|string',
            'delid' => 'nullable|integer',
            'edited' => 'nullable|string',
            'id' => 'nullable|integer',
            'add' => 'nullable|string',
            'name' => 'nullable|string|max:255',
            'flagpic' => 'nullable|string|max:255',
            'location_main' => 'nullable|string|max:255',
            'location_sub' => 'nullable|string|max:255',
            'start_ip' => 'nullable|string|max:45',
            'end_ip' => 'nullable|string|max:45',
            'theory_upspeed' => 'nullable|string|max:50',
            'practical_upspeed' => 'nullable|string|max:50',
            'theory_downspeed' => 'nullable|string|max:50',
            'practical_downspeed' => 'nullable|string|max:50',
        ];
    }
}
