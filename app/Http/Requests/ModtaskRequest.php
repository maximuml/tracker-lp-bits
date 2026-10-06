<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REST rename of a legacy single-purpose POST URI — fields stay
 * permissive so the controller's legacy abort pages remain
 * byte-identical.
 */
class ModtaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => 'nullable|string|max:64',
            'userid' => 'nullable|integer',
            'confirm' => 'nullable|string|max:32',
            'warned' => 'nullable|string|max:32',
            'warnlength' => 'nullable|integer',
            'warnpm' => 'nullable|string',
            'title' => 'nullable|string|max:255',
            'uploadpos' => 'nullable|string|max:32',
            'downloadpos' => 'nullable|string|max:32',
            'forumpost' => 'nullable|string|max:32',
            'support' => 'nullable|string|max:32',
            'moviepicker' => 'nullable|string|max:32',
            'donor' => 'nullable|string|max:32',
            'resetkey' => 'nullable|string|max:32',
            'modcomment' => 'nullable|string',
            'enabled' => 'nullable|string|max:32',
            'signature' => 'nullable|string',
            'avatar' => 'nullable|string|max:255',
            'seedbonus' => 'nullable',
            'class' => 'nullable|integer',
        ];
    }
}
