<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserMedalStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'medal_id' => 'required|integer',
            'uid' => 'required|integer',
            'duration' => 'nullable|integer|min:-1',
        ];
    }
}
