<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SecuritySaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'resetpasskey' => 'nullable|string',
            'resetauthkey' => 'nullable|string',
            'email' => 'nullable|string',
            'chpassword' => 'nullable|string',
            'privacy' => 'nullable|string',
            'two_step_secret' => 'nullable|string',
            'two_step_code' => 'nullable|string',
            'oldpassword' => 'nullable|string',
        ];
    }
}
