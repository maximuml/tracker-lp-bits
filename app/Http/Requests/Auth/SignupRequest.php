<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'wantusername' => ['nullable', 'string'],
            'wantpassword' => ['nullable', 'string'],
            'wantpassword_hashed' => ['nullable', 'in:0,1'],
            'passagain' => ['nullable', 'string'],
            'email' => ['nullable', 'string'],
            'country' => ['nullable', 'integer'],
            'gender' => ['nullable', 'string'],
            'rulesverify' => ['nullable', 'in:yes'],
            'faqverify' => ['nullable', 'in:yes'],
            'ageverify' => ['nullable', 'in:yes'],
            'imagehash' => ['nullable', 'string'],
            'imagestring' => ['nullable', 'string'],
            'type' => ['nullable', 'in:normal,invite'],
            'inviter' => ['nullable', 'integer'],
            'hash' => ['nullable', 'string'],
        ];
    }
}
