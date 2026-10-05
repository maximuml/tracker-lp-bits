<?php

declare(strict_types=1);

namespace App\Http\Requests\Ajax;

final class ClaimTaskRequest extends AjaxFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'exam_id' => 'required|integer|min:1',
        ];
    }
}
