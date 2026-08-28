<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserResetPasswordRequest extends FormRequest
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
            'password' => 'required|string|min:6|max:40',
            'password_confirmation' => 'required|same:password',
        ];
    }
}
