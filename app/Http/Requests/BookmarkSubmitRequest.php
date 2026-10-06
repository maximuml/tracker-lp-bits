<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REST rename of a legacy single-purpose POST URI — fields stay
 * permissive so the controller's legacy abort pages remain
 * byte-identical.
 */
class BookmarkSubmitRequest extends FormRequest
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
            'action' => 'nullable|string|max:64',
            'torrentid' => 'nullable|integer',
            'del' => 'nullable|string|max:32',
            'submit' => 'nullable|string|max:64',
        ];
    }
}
