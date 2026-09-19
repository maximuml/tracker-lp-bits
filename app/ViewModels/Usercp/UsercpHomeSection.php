<?php

declare(strict_types=1);

namespace App\ViewModels\Usercp;

use App\Support\Html\SafeHtml;

/**
 * Home section of the user control panel.
 *
 * `ipLocation`/`passkey` are `Strings::hidden()` output (safe markup);
 * `readTopics` items render timestamps through `<x-time>`.
 * `passkeyLogin` is null unless passkey login is enabled and the
 * login-secret deadline is in the future.
 */
final readonly class UsercpHomeSection
{
    /**
     * @param  list<ReadTopicItem>  $readTopics
     */
    public function __construct(
        public ?string $joinDate,
        public string $email,
        public SafeHtml $ipLocation,
        public bool $showAvatar,
        public string $avatarUrl,
        public SafeHtml $passkey,
        public ?PasskeyLoginForm $passkeyLogin,
        public int $invites,
        public string $seedbonus,
        public int $commentCount,
        public ?UsercpPostStats $forumPosts,
        public UsercpTokenSection $tokens,
        public array $readTopics,
        public int $userId,
        public string $invitesLinkTitle,
        public string $karmaLinkTitle,
        public string $commentsLinkTitle,
        public string $postsLinkTitle,
    ) {}
}
