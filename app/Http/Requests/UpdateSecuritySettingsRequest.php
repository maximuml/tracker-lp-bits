<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-05: Validation for legacy POST /usercp with action=security, type=confirm.
 */
class UpdateSecuritySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:security',
            'type' => 'required|string|in:confirm',
            'response' => 'sometimes|nullable|string|max:500',
            'oldpassword' => 'sometimes|nullable|string|max:200',
            'email' => 'sometimes|nullable|email|max:255',
            'chpassword' => 'sometimes|nullable|string|min:6|max:40',
            'privacy' => 'sometimes|in:normal,low,strong',
            'resetpasskey' => 'sometimes|in:0,1',
            'resetauthkey' => 'sometimes|in:0,1',
            'two_step_secret' => 'sometimes|nullable|string|max:500',
            'two_step_code' => 'sometimes|nullable|string|max:100',
        ];
    }
}
