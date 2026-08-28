<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserIncrementDecrementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uid' => 'required',
            'action' => 'required',
            'field' => 'required',
            'value' => 'required|numeric',
        ];
    }
}
