<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Poll;
use Illuminate\Foundation\Http\FormRequest;

class PollVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'poll_id' => 'required|integer',
            'choice' => 'required|integer|min:0|max:'.Poll::MAX_OPTION_INDEX,
        ];
    }
}
