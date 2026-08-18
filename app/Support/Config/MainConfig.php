<?php

declare(strict_types=1);

namespace App\Support\Config;

final class MainConfig extends Config
{
    public function announceInterval(int $default = 1800): int
    {
        return $this->int('announce_interval', $default);
    }

    public function annintertwoage(int $default = 0): int
    {
        return $this->int('annintertwoage', $default);
    }

    public function annintertwo(int $default = 0): int
    {
        return $this->int('annintertwo', $default);
    }

    public function anninterthreeage(int $default = 0): int
    {
        return $this->int('anninterthreeage', $default);
    }

    public function anninterthree(int $default = 0): int
    {
        return $this->int('anninterthree', $default);
    }

    public function autocleanIntervalOne(int $default = 900): int
    {
        return $this->int('autoclean_interval_one', $default);
    }

    public function autocleanInterval(string $level, int $default = 0): int
    {
        return $this->int("autoclean_interval_{$level}", $default);
    }

    public function browseCat(int $default = 0): int
    {
        return $this->int('browsecat', $default);
    }

    public function category(string $key, int $default = 0): int
    {
        return $this->int("{$key}cat", $default);
    }

    public function defaultLang(string $default = ''): string
    {
        return $this->string('defaultlang', $default);
    }

    public function enableNfo(bool $default = false): bool
    {
        return $this->bool('enablenfo', $default);
    }

    public function iniUpload(int $default = 0): int
    {
        return $this->int('iniupload', $default);
    }

    public function inviteTimeout(int $default = 7): int
    {
        return $this->int('invite_timeout', $default);
    }

    public function maxDeadTorrentTime(int $default = 21600): int
    {
        return $this->int('max_dead_torrent_time', $default);
    }

    public function maxDlSystem(bool $default = false): bool
    {
        return $this->bool('maxdlsystem', $default);
    }

    public function offerSkipApprovedCount(int $default = 0): int
    {
        return $this->int('offer_skip_approved_count', $default);
    }

    public function offerUploadTimeout(int $default = 86400): int
    {
        return $this->int('offeruptimeout', $default);
    }

    public function offerVoteTimeout(int $default = 259200): int
    {
        return $this->int('offervotetimeout', $default);
    }

    public function signupTimeout(int $default = 259200): int
    {
        return $this->int('signup_timeout', $default);
    }

    public function siteEmail(string $default = ''): string
    {
        return $this->string('SITEEMAIL', $default);
    }

    public function torrentDir(string $default = ''): string
    {
        return $this->string('torrent_dir', $default);
    }

    public function torrentsPerPage(int $default = 0): int
    {
        return $this->int('torrentsperpage', $default);
    }

    public function uploadDenyApprovalDenyCount(int $default = 0): int
    {
        return $this->int('upload_deny_approval_deny_count', $default);
    }

    public function maxTorrentSize(int $default = 0): int
    {
        return $this->int('max_torrent_size', $default);
    }

    public function waitSystem(bool $default = false): bool
    {
        return $this->bool('waitsystem', $default);
    }

    public function maxUsers(int $default = 0): int
    {
        return $this->int('maxusers', $default);
    }

    public function inviteSystem(bool $default = false): bool
    {
        return $this->bool('invitesystem', $default);
    }

    public function showOffer(bool $default = false): bool
    {
        return $this->bool('showoffer', $default);
    }

    public function siteLanguageEnabled(bool $default = true): bool
    {
        return $this->bool('site_language_enabled', $default);
    }

    public function torrentNamePrefix(string $default = ''): string
    {
        return $this->string('torrentnameprefix', $default);
    }

    public function registration(bool $default = false): bool
    {
        return $this->bool('registration', $default);
    }

    public function reportEmail(string $default = ''): string
    {
        return $this->string('reportemail', $default);
    }

    public function tmpInviteCount(int $default = 0): int
    {
        return $this->int('tmp_invite_count', $default);
    }

    public function verification(string $default = 'email'): string
    {
        return $this->string('verification', $default);
    }

    public function maxIp(int $default = 0): int
    {
        return $this->int('maxip', $default);
    }

    public function isUploadOpenAtWeekend(bool $default = false): bool
    {
        return $this->bool('sptime', $default);
    }

    public function defStylesheet(int $default = 1): int
    {
        return $this->int('defstylesheet', $default);
    }

    public function inviteCount(int $default = 0): int
    {
        return $this->int('invite_count', $default);
    }

    public function showTopUploader(bool $default = false): bool
    {
        return $this->bool('show_top_uploader', $default);
    }

    public function enableTechnicalInfo(bool $default = false): bool
    {
        return $this->bool('enable_technical_info', $default);
    }

    /**
     * @param array<int, string> $default
     * @return array<int, string>
     */
    public function enabledSiteLanguages(array $default = ['en']): array
    {
        /** @var array<int, string> $value */
        $value = $this->array('site_language_enabled', $default);
        return array_values(array_map('strval', $value));
    }

    public function torrentsPerPageNullable(?string $default = null): ?string
    {
        $value = $this->data['torrentsperpage'] ?? $default;
        return $value !== null ? (string) $value : null;
    }

}
