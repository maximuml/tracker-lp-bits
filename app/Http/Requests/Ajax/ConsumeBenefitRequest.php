<?php

declare(strict_types=1);

namespace App\Http\Requests\Ajax;

use App\Models\UserMeta;

final class ConsumeBenefitRequest extends AjaxFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'meta_key' => 'required|string|in:'.UserMeta::META_KEY_CHANGE_USERNAME,
            'username' => 'required|string|min:1',
        ];
    }
}
