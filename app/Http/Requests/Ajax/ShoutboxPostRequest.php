<?php

declare(strict_types=1);

namespace App\Http\Requests\Ajax;

final class ShoutboxPostRequest extends AjaxFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'text' => 'sometimes|string',
            'content' => 'sometimes|string',
        ];
    }
}
