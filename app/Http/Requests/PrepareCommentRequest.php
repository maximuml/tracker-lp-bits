<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrepareCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $allTypes = array_keys(Comment::TYPE_MAPS);

        return [
            'type' => ['required', Rule::in($allTypes)],
            'torrent_id' => 'nullable|integer',
            'text' => 'required',
            'offer_id' => 'nullable|integer',
            'request_id' => 'nullable|integer',
            'anonymous' => 'nullable',
        ];
    }
}
