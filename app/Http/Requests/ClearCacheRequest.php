<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/system/clear-cache — the REST rename of POST /clearcache
 * (staff/tool endpoint). Fields stay permissive: the controller keeps
 * its legacy abort pages byte-identical.
 */
class ClearCacheRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cachename' => 'nullable|string',
            'multilang' => 'nullable|string',

        ];
    }
}
