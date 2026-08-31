<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExamRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'indexes' => 'required|array|min:1',
            'indexes.*.index' => ['required', Rule::in(array_keys(Exam::$indexes))],
            'indexes.*.require_value' => 'nullable|numeric',
            'status' => 'required|in:0,1',
            'duration' => 'nullable|numeric',
        ];
    }
}
