<?php

declare(strict_types=1);

namespace App\Http\Requests;

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
            // Allow 255 as a special "blank vote" value — it is recorded but
            // excluded from result counts by IndexRepository::getPollResults().
            'choice' => 'required|integer|min:0|max:255',
        ];
    }
}
