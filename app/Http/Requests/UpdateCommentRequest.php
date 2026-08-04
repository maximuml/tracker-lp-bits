<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'type' => 'required|string|in:torrent,offer',
            'body' => 'required|string|max:65535',
            'returnto' => 'nullable|string|max:1024',
        ];
    }
}
