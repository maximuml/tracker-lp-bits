<?php

declare(strict_types=1);

namespace App\Http\Requests\Ajax;

final class PasskeyCreateRequest extends AjaxFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'challengeId' => 'required|string',
            'clientDataJSON' => 'required|string',
            'attestationObject' => 'required|string',
        ];
    }
}
