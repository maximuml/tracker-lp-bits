<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/invites/send — the REST rename of POST /takeinvite
 * (send an invitation email). The domain checks (username length,
 * email sanity, invite quotas) stay in the controller.
 */
class SendInviteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer',
            'email' => 'nullable|string|max:255',
            'pre_register_username' => 'nullable|string|max:255',
            'setinvite' => 'nullable|string',
        ];
    }
}
