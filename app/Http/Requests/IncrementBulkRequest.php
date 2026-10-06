<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/system/increment-bulk — the REST rename of POST /take-increment-bulk
 * (sysop bulk-increments a stat field for user classes). The domain
 * validation (numeric amount, valid type map) stays in the controller.
 */
class IncrementBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sender' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'msg' => 'nullable|string',
            'amount' => 'nullable',
            'type' => 'nullable|string',
            'classes' => 'nullable|array',
            'classes.*' => 'nullable',
            'duration' => 'nullable|integer|min:0',
        ];
    }
}
