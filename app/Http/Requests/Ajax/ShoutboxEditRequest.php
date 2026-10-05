<?php

declare(strict_types=1);

namespace App\Http\Requests\Ajax;

final class ShoutboxEditRequest extends AjaxFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => 'required|integer|min:1',
            'text' => 'required|string',
        ];
    }
}
