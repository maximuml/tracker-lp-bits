<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * Legacy /ajax callers post `{action: string, params: {...}}`; the 308
 * redirect replays that body unchanged, so REST endpoints accept both
 * shapes: flat fields (new clients) and the params envelope (redirected
 * legacy callers).
 */
trait FlattensAjaxEnvelope
{
    protected function prepareForValidation(): void
    {
        $params = $this->input('params');
        if (is_array($params)) {
            $this->merge($params);
        }
    }
}
