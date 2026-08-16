<?php

declare(strict_types=1);

namespace App\DTOs\Usercp;

use Illuminate\Http\Request;

/**
 * Immutable DTO for security settings on the user control panel API.
 */
final readonly class SecuritySettingsDto
{
    /**
     * @param  array<string, mixed>  $allInputs  Original request inputs for compatibility hooks.
     */
    public function __construct(
        public string $currentPassword,
        public ?string $email,
        public ?string $newPassword,
        public ?string $privacy,
        public bool $resetpasskey,
        public bool $resetauthkey,
        public ?string $twoStepSecret,
        public ?string $twoStepCode,
        public string $ip,
        public array $allInputs,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'email' => 'sometimes|nullable|email',
            'new_password' => 'sometimes|nullable|string|min:6|max:40',
            'new_password_confirmation' => 'sometimes|same:new_password',
            'privacy' => 'sometimes|in:normal,low,strong',
            'resetpasskey' => 'sometimes|boolean',
            'resetauthkey' => 'sometimes|boolean',
            'two_step_secret' => 'sometimes|nullable|string',
            'two_step_code' => 'sometimes|nullable|string',
        ]);

        $privacy = (string) ($validated['privacy'] ?? '');

        return new self(
            (string) $validated['current_password'],
            isset($validated['email']) ? (string) $validated['email'] : null,
            isset($validated['new_password']) ? (string) $validated['new_password'] : null,
            $privacy !== '' ? $privacy : null,
            ! empty($validated['resetpasskey']),
            ! empty($validated['resetauthkey']),
            isset($validated['two_step_secret']) ? (string) $validated['two_step_secret'] : null,
            isset($validated['two_step_code']) ? (string) $validated['two_step_code'] : null,
            (string) ($request->ip() ?? ''),
            (array) $request->all(),
        );
    }
}
