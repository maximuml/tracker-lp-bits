<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * POST /web/messages/mailboxes — replaces legacy POST /messages.php with
 * action=editmailboxes2 (custom mailbox add/rename/delete, switched by
 * action2=add|edit inside the mailbox service).
 */
class EditMailboxesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action2' => 'nullable|string',
            'new1' => 'nullable|string|max:14',
            'new2' => 'nullable|string|max:14',
            'new3' => 'nullable|string|max:14',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect('/messages.php?action=editmailboxes')->withErrors($validator)->withInput()
        );
    }
}
