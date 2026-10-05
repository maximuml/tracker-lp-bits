<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\FlattensAjaxEnvelope;
use Illuminate\Foundation\Http\FormRequest;

class TokenDeleteRequest extends FormRequest
{
    use FlattensAjaxEnvelope;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => 'required|integer',
        ];
    }
}
