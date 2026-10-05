<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/news/edit — replaces legacy POST /web/news with action=edit.
 * `newsid` may still arrive as a query param of the form action URL.
 */
class NewsEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'newsid' => 'nullable',
            'body' => 'nullable|string',
            'subject' => 'nullable|string',
            'notify' => 'nullable|string',
            'returnto' => 'nullable|string',
        ];
    }
}
