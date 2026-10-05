<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * POST /web/offers/delete — replaces legacy POST /offers.php carrying the
 * marker param; the URI is the verb now, the service staff or owner deletes (id, sure, reason).
 */
class DeleteOfferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect('/offers.php')->withErrors($validator)->withInput()
        );
    }
}
