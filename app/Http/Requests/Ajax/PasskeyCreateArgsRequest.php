<?php

declare(strict_types=1);

namespace App\Http\Requests\Ajax;

final class PasskeyCreateArgsRequest extends AjaxFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
