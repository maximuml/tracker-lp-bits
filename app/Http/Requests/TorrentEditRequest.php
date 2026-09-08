<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * W1-06: Validation for legacy POST /takeedit.
 */
class TorrentEditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'id' => 'required|integer|min:1',
            'name' => 'required|string|min:1|max:255',
            'descr' => 'required|string|min:1',
            'type' => 'required|integer|min:1',
            'anonymous' => 'sometimes|integer|in:0,1',
            'visible' => 'sometimes|integer|in:0,1',
            'price' => 'sometimes|integer|min:0',
            'sel_spstate' => 'sometimes|integer|in:2,3,4,5,6,7',
            'promotion_time_type' => 'sometimes|integer|in:0,1,2',
            'promotionuntil' => 'sometimes|nullable|string|max:30',
            'pos_state' => 'sometimes|integer',
            'pos_state_until' => 'sometimes|nullable|string|max:30',
            'cover' => 'sometimes|nullable|string|max:500',
            'technical_info' => 'sometimes|nullable|string|max:5000',
            'returnto' => 'sometimes|nullable|string|max:500',
        ];
    }
}
