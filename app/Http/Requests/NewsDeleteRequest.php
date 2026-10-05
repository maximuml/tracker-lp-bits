<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/news/delete — replaces legacy POST /news.php with
 * action=delete. Without `sure=1` the controller renders the confirm step.
 */
class NewsDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'newsid' => 'required',
            'sure' => 'nullable',
            'returnto' => 'nullable|string',
        ];
    }
}
