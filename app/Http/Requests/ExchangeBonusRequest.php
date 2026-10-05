<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * POST /web/mybonus/exchange — replaces legacy POST /mybonus.php?action=exchange
 * (karma-shop purchase; `option` + art-specific fields are validated by the
 * service's cheat checks, which render the legacy error page).
 */
class ExchangeBonusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'option' => 'nullable',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect('/mybonus.php')->withErrors($validator)->withInput()
        );
    }
}
