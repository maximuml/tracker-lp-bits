<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-06: Validation for legacy POST /takeupload.
 */
class TorrentUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|nullable|string|max:255',
            'descr' => 'required|string|min:1',
            'type' => 'required|integer|min:1',
            'anonymous' => 'sometimes|in:yes,1',
            'offer_id' => 'sometimes|nullable|integer|min:0',
            'price' => 'sometimes|integer|min:0',
            'cover' => 'sometimes|nullable|string|max:500',
            'technical_info' => 'sometimes|nullable|string|max:30000',
            'file' => 'required|file|mimetypes:application/x-bittorrent|max:2048',
        ];
    }
}
