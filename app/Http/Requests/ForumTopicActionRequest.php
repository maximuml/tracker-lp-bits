<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-04: Validation for legacy POST /forums with action=setlocked|setsticky|hltopic.
 */
class ForumTopicActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:setlocked,setsticky,hltopic',
            'topicid' => 'required|integer|min:1',
            'locked' => 'nullable|boolean',
            'sticky' => 'nullable|string|in:yes,no',
            'color' => 'nullable|integer|min:0',
            'returnto' => 'nullable|string|max:500',
        ];
    }
}
