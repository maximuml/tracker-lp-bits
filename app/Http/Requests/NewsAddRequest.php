<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/news/add — replaces legacy POST /web/news with action=add.
 * Empty body/subject surface the legacy error pages, so they stay nullable
 * here and the controller keeps the domain checks.
 */
class NewsAddRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => 'nullable|string',
            'subject' => 'nullable|string',
            'added' => 'nullable',
            'notify' => 'nullable|string',
            'returnto' => 'nullable|string',
        ];
    }
}
