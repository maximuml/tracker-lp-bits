<?php

declare(strict_types=1);

namespace App\Models\Traits;

/**
 * Legacy and API array conversion helpers for the User model.
 */
trait HasUserApi
{
    /**
     * Return the user as an array with the legacy columns that are normally
     * hidden (passkey, auth_key) included. Used when populating SupportContext
     * for legacy views that need those values.
     *
     * @return array<string, mixed>
     */
    public function toLegacyArray(): array
    {
        return $this->makeVisible(['passkey', 'auth_key'])->toArray();
    }

    /**
     * Convert the model to an array with enum-cast attributes serialized
     * as their string values for API responses.
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $data = $this->toArray();
        foreach (self::$ENUM_STRING_KEYS as $key => $enumClass) {
            $raw = $this->getAttributes()[$key] ?? null;
            if ($raw !== null) {
                /** @var \BackedEnum $enum */
                $enum = $enumClass::from($raw);
                $data[$key] = method_exists($enum, 'stringValue')
                    ? $enum->stringValue()
                    : $enum->value;
            }
        }

        return $data;
    }
}
