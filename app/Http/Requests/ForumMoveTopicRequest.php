<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-04: Validation for legacy POST /forums with action=movetopic.
 */
class ForumMoveTopicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:movetopic',
            'forumid' => 'required|integer|min:1',
            'topicid' => 'required|integer|min:1',
        ];
    }
}
