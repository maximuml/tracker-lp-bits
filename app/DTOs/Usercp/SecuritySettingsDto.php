<?php

declare(strict_types=1);

namespace App\DTOs\Usercp;

use App\Enums\UserPrivacy;
use Illuminate\Http\Request;

/**
 * Immutable DTO for security settings on the user control panel API.
 */
final readonly class SecuritySettingsDto
{
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
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'email' => 'sometimes|nullable|email',
            'new_password' => 'sometimes|nullable|string|min:6|max:40',
            'new_password_confirmation' => 'sometimes|same:new_password',
            'privacy' => 'sometimes|in:normal,low,strong,0,1,2',
            'resetpasskey' => 'sometimes|boolean',
            'resetauthkey' => 'sometimes|boolean',
            'two_step_secret' => 'sometimes|nullable|string',
            'two_step_code' => 'sometimes|nullable|string',
        ]);

        $privacyRaw = $validated['privacy'] ?? '';
        $privacy = self::normalizePrivacy($privacyRaw);

        return new self(
            (string) $validated['current_password'],
            isset($validated['email']) ? (string) $validated['email'] : null,
            isset($validated['new_password']) ? (string) $validated['new_password'] : null,
            $privacy,
            ! empty($validated['resetpasskey']),
            ! empty($validated['resetauthkey']),
            isset($validated['two_step_secret']) ? (string) $validated['two_step_secret'] : null,
            isset($validated['two_step_code']) ? (string) $validated['two_step_code'] : null,
            (string) ($request->ip() ?? ''),
        );
    }

    private static function normalizePrivacy(mixed $value): ?string
    {
        if ($value === '' || $value === null) {
            return null;
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return UserPrivacy::from((int) $value)->stringValue();
        }

        return (string) $value;
    }
}
