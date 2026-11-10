<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class TorrentIdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'torrent_id' => 'required|integer|min:1',
        ];
    }

    /**
     * The approval page is a GET endpoint: the default redirect-back on
     * validation failure sends the browser to the same URL, producing an
     * infinite redirect loop when torrent_id is missing or invalid.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new NotFoundHttpException;
    }
}
