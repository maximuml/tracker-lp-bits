<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * POST /web/messages/delete — replaces legacy POST /messages.php with
 * action=deletemessage (mailbox single-message delete; distinct from
 * POST /deletemessage.php which carries the type=in|out semantics).
 */
class DeleteMailboxMessageRequest extends FormRequest
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
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect('/web/messages')->withErrors($validator)->withInput()
        );
    }
}
