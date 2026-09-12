<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-05: Validation for legacy POST /usercp with action=personal, type=save.
 */
class UpdatePersonalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:personal',
            'type' => 'required|string|in:save',
            'parked' => 'sometimes|in:yes',
            'acceptpms' => 'sometimes|in:yes,friends,no,0,1,2',
            'deletepms' => 'sometimes',
            'savepms' => 'sometimes',
            'commentpm' => 'sometimes|in:yes',
            'gender' => 'sometimes|in:N/A,Male,Female,0,1,2',
            'country' => 'sometimes|integer|min:0',
            'tracker_url_id' => 'sometimes|integer|min:0',
            'avatar' => 'sometimes|nullable|string|max:500',
            'savatar' => 'sometimes|nullable|string|max:500',
            'info' => 'sometimes|nullable|string|max:30000',
            'notifs' => 'sometimes|array',
        ];
    }
}
