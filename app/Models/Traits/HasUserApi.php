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
        // Raw column values — legacy $CURUSER was the SELECT * row: no
        // accessor/cast transforms (avatar default, datetime casts) so
        // value('avatar')/value('added')/yes()/no() see faithful data.
        // two_step_secret stays in the channel for the same reason as
        // passkey/auth_key: internal views (usercp security page, login
        // flow) read it off the current-actor array.
        return array_diff_key(
            $this->getAttributes(),
            array_flip(array_diff($this->getHidden(), ['passkey', 'auth_key', 'two_step_secret'])),
        );
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
