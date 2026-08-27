<?php

declare(strict_types=1);

namespace App\Support\Config;

use App\Support\Settings;

final class SiteConfig
{
    public readonly MainConfig $main;

    public readonly TorrentConfig $torrent;

    public readonly AccountConfig $account;

    public readonly SecurityConfig $security;

    public readonly SystemConfig $system;

    public readonly MeiliSearchConfig $meiliSearch;

    public readonly BasicConfig $basic;

    public readonly BonusConfig $bonus;

    public readonly AttachmentConfig $attachment;

    public readonly SmtpConfig $smtp;

    public readonly BackupConfig $backup;

    public readonly CaptchaConfig $captcha;

    public readonly AuthorityConfig $authority;

    public readonly ImageHostingConfig $imageHosting;

    /** @param array<string, mixed> $data */
    public function __construct(array $data = [])
    {
        $this->main = new MainConfig($data['main'] ?? []);
        $this->torrent = new TorrentConfig($data['torrent'] ?? []);
        $this->account = new AccountConfig($data['account'] ?? []);
        $this->security = new SecurityConfig($data['security'] ?? []);
        $this->system = new SystemConfig($data['system'] ?? []);
        $this->meiliSearch = new MeiliSearchConfig($data['meilisearch'] ?? []);
        $this->basic = new BasicConfig($data['basic'] ?? []);
        $this->bonus = new BonusConfig($data['bonus'] ?? []);
        $this->attachment = new AttachmentConfig($data['attachment'] ?? []);
        $this->smtp = new SmtpConfig($data['smtp'] ?? []);
        $this->backup = new BackupConfig($data['backup'] ?? []);
        $this->captcha = new CaptchaConfig($data['captcha'] ?? []);
        $this->authority = new AuthorityConfig($data['authority'] ?? []);

        $imageHostingData = $data['image_hosting'] ?? [];
        if (! empty($data['image_hosting_chevereto'])) {
            $imageHostingData['chevereto'] = $data['image_hosting_chevereto'];
        }
        if (! empty($data['image_hosting_lsky'])) {
            $imageHostingData['lsky'] = $data['image_hosting_lsky'];
        }
        $this->imageHosting = new ImageHostingConfig($imageHostingData);
    }

    public static function current(): self
    {
        /** @var array<string, mixed> $settings */
        $settings = Settings::get() ?: [];

        return new self($settings);
    }

    public static function fromDb(): self
    {
        /** @var array<string, mixed> $settings */
        $settings = Settings::fromDb() ?: [];

        return new self($settings);
    }
}
