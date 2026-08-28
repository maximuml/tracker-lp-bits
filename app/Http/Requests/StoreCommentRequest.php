<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
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
            'pid' => 'required|integer|min:1',
            'body' => 'required|string|max:65535',
        ];
    }
}
