<?php

declare(strict_types=1);

namespace App\Http\Requests\Ajax;

final class PasskeyGetRequest extends AjaxFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'challengeId' => 'required|string',
            'id' => 'required|string',
            'clientDataJSON' => 'required|string',
            'authenticatorData' => 'required|string',
            'signature' => 'required|string',
            'userHandle' => 'nullable|string',
        ];
    }
}
