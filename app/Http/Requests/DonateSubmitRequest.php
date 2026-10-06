<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REST rename of a legacy single-purpose POST URI — fields stay
 * permissive so the controller's legacy abort pages remain
 * byte-identical.
 */
class DonateSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'usd' => 'nullable',
            'id' => 'nullable|integer',
            'msg' => 'nullable|string',
            'anonymous' => 'nullable|string|max:32',
            'payment_status' => 'nullable|string|max:64',
        ];
    }
}
