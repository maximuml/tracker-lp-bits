<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class PasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1'],
            'secret' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'password' => ['required', 'string', 'min:6', 'max:40', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    protected function getRedirectUrl(): string
    {
        $query = array_filter([
            'id' => $this->input('id'),
            'secret' => $this->input('secret'),
        ], static fn ($value) => $value !== null && $value !== '');

        return '/recover'.($query === [] ? '' : '?'.http_build_query($query));
    }
}
