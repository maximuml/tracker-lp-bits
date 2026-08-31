<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\AgentAllow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AgentAllowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'family' => 'required|string',
            'start_name' => 'required|string',
            'peer_id_pattern' => 'required|string',
            'peer_id_match_num' => 'required|numeric',
            'peer_id_matchtype' => ['required', Rule::in(array_keys(AgentAllow::$matchTypes))],
            'peer_id_start' => 'required|string',
            'agent_pattern' => 'required|string',
            'agent_match_num' => 'required|numeric',
            'agent_matchtype' => ['required', Rule::in(array_keys(AgentAllow::$matchTypes))],
            'agent_start' => 'required|string',
            'exception' => ['required', Rule::in(['yes', 'no'])],
            'allowhttps' => ['required', Rule::in(['yes', 'no'])],
        ];
    }
}
