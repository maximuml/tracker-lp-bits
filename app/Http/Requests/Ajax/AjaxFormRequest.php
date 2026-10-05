<?php

declare(strict_types=1);

namespace App\Http\Requests\Ajax;

use App\Http\Requests\Concerns\FlattensAjaxEnvelope;
use App\Support\Api;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Base class for the endpoints the /ajax action-string dispatcher now
 * 308-redirects to. Keeps the legacy `{ret, msg, data}` wire format for
 * validation failures instead of the default 422 JSON shape, so old
 * callers (which read `response.ret`) keep working byte-identically.
 */
abstract class AjaxFormRequest extends FormRequest
{
    use FlattensAjaxEnvelope;

    /**
     * Authentication is enforced by the route middleware (auth.nexus),
     * same as the old /ajax flow's requireLoginFromContext().
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(Api::failWithContext($validator->errors()->first()))
        );
    }
}
