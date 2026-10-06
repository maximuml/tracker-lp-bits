<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /web/system/amount-upload — the REST rename of POST /takeamountupload
 * (sysop bulk-credits upload amount to user classes). Fields stay
 * permissive: the controller aborts with the legacy error pages.
 */
class AmountUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'sender' => 'nullable|string',
            'subject' => 'nullable|string|max:255',
            'msg' => 'nullable|string',
            'amount' => 'nullable',
            'clases' => 'nullable|array',
            'clases.*' => 'nullable',
        ];
    }
}
