<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-05: Validation for legacy POST /usercp with action=forum, type=save.
 */
class UpdateForumSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'required|string|in:forum',
            'type' => 'required|string|in:save',
            'topicsperpage' => 'sometimes|integer|min:0|max:100',
            'postsperpage' => 'sometimes|integer|min:0|max:100',
            'avatars' => 'sometimes|in:yes',
            'signatures' => 'sometimes|in:yes',
            'clicktopic' => 'sometimes|in:firstpage,lastpage,0,1',
            'signature' => 'sometimes|nullable|string|max:30000',
            'ttlastpost' => 'sometimes|in:yes',
        ];
    }
}
