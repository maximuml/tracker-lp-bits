<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-03: Validation for legacy POST /messages with action=moveordel.
 */
class MoveOrDeleteMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:moveordel,editmailboxes2,deletemessage,viewmessage,viewmailbox',
            'id' => 'nullable|integer|min:0',
            'box' => 'nullable|integer|min:0',
            'messages' => 'nullable|array',
            'messages.*' => 'integer|min:1',
            'markread' => 'nullable|string',
            'move' => 'nullable|string',
            'delete' => 'nullable|string',
        ];
    }
}
