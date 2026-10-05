<?php

declare(strict_types=1);

namespace App\Http\Requests\Ajax;

final class RemoveLeechWarnRequest extends AjaxFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'uid' => 'required|integer|min:1',
        ];
    }
}
