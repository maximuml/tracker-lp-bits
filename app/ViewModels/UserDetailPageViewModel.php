<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Models\User;
use App\Support\Html\SafeHtml;
use Illuminate\Contracts\Support\Renderable;

/**
 * ViewModel for the user details page.
 *
 * Assembled by UserDetailPageService::build() and handed to the view
 * as `vm` — every pre-computed view key is a typed property.
 */
final class UserDetailPageViewModel extends ViewModel
{
    /**
     * @param  array<int|string, mixed>  $user  Legacy user row from UserDetailRepository::getUser()
     * @param  array<int|string, mixed>  $currentUser
     * @param  array<int|string, mixed>  $locationInfo
     * @param  array<int|string, mixed>  $trueTraffic
     * @param  list<array{type: string, label: string}>  $torrentListSections
     * @param  list<Renderable>  $userProps
     */
    public function __construct(
        public readonly int $id,
        public readonly array $user,
        public readonly ?User $userModel,
        public readonly int $torrentcomments,
        public readonly int $forumposts,
        public readonly int $temporaryInviteCount,
        public readonly string $modcomment,
        public readonly string $bonuscomment,
        public readonly bool $isOwner,
        public readonly array $currentUser,
        public readonly bool $canViewConfidential,
        public readonly bool $canViewHistory,
        public readonly bool $canManageBasic,
        public readonly bool $canManageConfidential,
        public readonly bool $canViewTorrentHistory,
        public readonly bool $canViewInvite,
        public readonly int $currentClass,
        public readonly bool $isFriend,
        public readonly bool $currentUserBlockedTarget,
        public readonly bool $targetBlockedMe,
        public readonly bool $currentUserIsFriendOfTarget,
        public readonly bool $showPmButton,
        public readonly string $countryFlagPic,
        public readonly string $countryName,
        public readonly array $locationInfo,
        public readonly string $locationInfoHtml,
        public readonly ?SafeHtml $clientSelectHtml,
        public readonly array $trueTraffic,
        public readonly float $trueDownload,
        public readonly float $trueUpload,
        public readonly ?float $shareRatio,
        public readonly ?float $trueRatio,
        public readonly ?float $seedLeechRatio,
        public readonly string $joinWeeks,
        public readonly bool $warned,
        public readonly bool $leechwarn,
        public readonly bool $canRemoveLeechWarn,
        public readonly string $elapsedLastWarn,
        public readonly ?string $warnedUntilPretty,
        public readonly ?string $leechwarnUntilPretty,
        public readonly ?SafeHtml $warnedByHtml,
        public readonly SafeHtml $migratedHelp,
        public readonly string $userManageSystemUrl,
        public readonly SafeHtml $usernameHtml,
        public readonly SafeHtml $invitedByHtml,
        public readonly SafeHtml|string $avatarHtml,
        public readonly SafeHtml $bonusTableHtml,
        public readonly SafeHtml $hrStatusHtml,
        public readonly int $ipHistoryCount,
        public readonly array $torrentListSections,
        public readonly array $userProps,
        public readonly string $triggerId,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
