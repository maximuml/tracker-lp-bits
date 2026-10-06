<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * REST rename of a legacy single-purpose POST URI — fields stay
 * permissive so the controller's legacy abort pages remain
 * byte-identical.
 */
class SettingsSubmitRequest extends FormRequest
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
            'siteoperate' => 'nullable|string|max:64',
            'sitenamename' => 'nullable|string|max:255',
            'siteurlurl' => 'nullable|string|max:255',
            'sitename' => 'nullable|string|max:255',
            'siteurl' => 'nullable|string|max:255',
        ];
    }
}
