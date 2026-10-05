<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ComplainNewRequest extends FormRequest
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
            'imagehash' => 'nullable|string',
            'imagestring' => 'nullable|string',
            'email' => 'nullable|string',
            'body' => 'nullable|string',
        ];
    }
}
