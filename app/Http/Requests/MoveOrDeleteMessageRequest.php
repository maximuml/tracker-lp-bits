<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * POST /web/messages/move-or-delete — replaces legacy POST /messages.php
 * with action=moveordel (mark-read / move / delete mailbox rows).
 */
class MoveOrDeleteMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => 'nullable|integer|min:0',
            'box' => 'nullable|integer|min:0',
            'messages' => 'nullable|array',
            'messages.*' => 'integer|min:1',
            'markread' => 'nullable|string',
            'move' => 'nullable|string',
            'delete' => 'nullable|string',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect('/web/messages')->withErrors($validator)->withInput()
        );
    }
}
