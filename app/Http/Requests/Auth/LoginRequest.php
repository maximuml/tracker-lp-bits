<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'two_step_code' => ['nullable', 'string'],
            'imagehash' => ['nullable', 'string'],
            'imagestring' => ['nullable', 'string'],
            'returnto' => ['nullable', 'string'],
            'logout' => ['nullable', 'in:yes,no'],
        ];
    }
}
