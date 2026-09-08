<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-04: Validation for legacy POST /forums with action=post.
 */
class ForumPostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:post',
            'type' => 'required|string|in:new,reply,edit',
            'id' => 'required|integer|min:1',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'postid' => 'nullable|integer|min:0',
        ];
    }
}
