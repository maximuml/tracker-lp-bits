<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IyuuApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => 'required|string',
            'id' => 'required|integer',
            'verity' => 'required|string',
            'provider' => ['required', 'string', Rule::in('iyuu')],
        ];
    }
}
