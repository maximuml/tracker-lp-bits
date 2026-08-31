<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Poll;
use Illuminate\Foundation\Http\FormRequest;

class PollUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return array_merge(
            ['question' => 'sometimes|string|max:255'],
            array_fill_keys(array_map(fn ($i) => "option{$i}", range(0, Poll::MAX_OPTION_INDEX)), 'sometimes|string|max:255')
        );
    }
}
