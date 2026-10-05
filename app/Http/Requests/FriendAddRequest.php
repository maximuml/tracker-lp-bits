<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/friends/add — replaces legacy POST /web/friends with action=add.
 * `id` is the friend-list owner (defaults to the current user), `targetid`
 * the user to add, `type` friend|block — resolved in the controller so the
 * legacy "Unknown type" error page stays intact.
 */
class FriendAddRequest extends FormRequest
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
            'targetid' => 'nullable',
            'type' => 'nullable|string',
        ];
    }
}
