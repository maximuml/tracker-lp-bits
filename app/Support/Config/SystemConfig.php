<?php

declare(strict_types=1);

namespace App\Support\Config;

final class SystemConfig extends Config
{
    public function alarmEmailReceiver(string $default = ''): string
    {
        return $this->string('alarm_email_receiver', $default);
    }

    public function cookieValidDays(int $default = 365): int
    {
        return $this->int('cookie_valid_days', $default);
    }

    public function maximumNumberOfMedalsCanBeWorn(int $default = 3): int
    {
        return $this->int('maximum_number_of_medals_can_be_worn', $default);
    }

    public function maximumUploadSpeed(int $default = 8000): int
    {
        return $this->int('maximum_upload_speed', $default);
    }

    public function accessAdminClassMin(?int $default = null): ?int
    {
        $value = $this->data['access_admin_class_min'] ?? $default;

        return $value !== null ? (int) $value : null;
    }

    public function changeUsernameMinIntervalInDays(int $default = 365): int
    {
        return $this->int('change_username_min_interval_in_days', $default);
    }

    public function changeUsernameCardAllowCharactersOutsideTheAlphabets(bool $default = false): bool
    {
        return $this->bool('change_username_card_allow_characters_outside_the_alphabets', $default);
    }

    public function isInvitePreEmailAndUsername(bool $default = false): bool
    {
        return $this->bool('is_invite_pre_email_and_username', $default);
    }

    public function meilisearchEnabled(bool $default = false): bool
    {
        return $this->bool('meilisearch_enabled', $default);
    }

    public function meilisearchSearchDescription(bool $default = false): bool
    {
        return $this->bool('meilisearch_search_description', $default);
    }

    public function isRecordSeedingBonusLog(bool $default = false): bool
    {
        return $this->bool('is_record_seeding_bonus_log', $default);
    }
}
