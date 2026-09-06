<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HitAndRunBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'uid' => 'nullable|array',
            'uid.*' => 'integer',
            'id' => 'nullable|array',
            'id.*' => 'integer',
        ];
    }
}
