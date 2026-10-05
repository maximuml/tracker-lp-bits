<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/friends/delete — replaces legacy POST /friends.php with
 * action=delete. Without `sure=1` the controller renders the confirm step.
 */
class FriendDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => 'nullable',
            'targetid' => 'required',
            'type' => 'nullable|string',
            'sure' => 'nullable',
        ];
    }
}
