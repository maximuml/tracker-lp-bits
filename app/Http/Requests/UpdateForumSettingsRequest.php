<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * W1-05: Validation for POST /web/usercp/forum.
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
            'topicsperpage' => 'sometimes|integer|min:0|max:100',
            'postsperpage' => 'sometimes|integer|min:0|max:100',
            'avatars' => 'sometimes|in:yes',
            'signatures' => 'sometimes|in:yes',
            'clicktopic' => 'sometimes|in:firstpage,lastpage,0,1',
            'signature' => 'sometimes|nullable|string|max:30000',
            'ttlastpost' => 'sometimes|in:yes',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect('/usercp.php?action=forum')->withErrors($validator)->withInput()
        );
    }
}
