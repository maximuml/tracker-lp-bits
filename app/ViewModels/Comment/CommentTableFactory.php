<?php

declare(strict_types=1);

namespace App\ViewModels\Comment;

use App\Auth\Permission;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\Time;
use App\Support\UserDisplay;

/**
 * Builds CommentTableViewModel from raw comment rows — the data assembly
 * that used to live inside Comment::table().
 */
final class CommentTableFactory
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function build(array $rows, string $type, int|string $parentId): CommentTableViewModel
    {
        $curUser = (array) ($this->currentUser->get() ?? []);
        $commanageClass = (int) SiteConfig::current()->authority->permission('commanage', 0);
        $contentWidth = \defined('CONTENT_WIDTH') ? (int) CONTENT_WIDTH : 100;

        $uidArr = array_values(array_filter(array_map('intval', array_column($rows, 'user'))));
        $neededColumns = ['id', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor', 'leechwarn', 'warned', 'title'];
        $userInfoArr = $this->userRepository->getByIds($uidArr, $neededColumns);

        $canManage = Permission::can(PermissionEnum::COM_MANAGE);
        $dt = date('Y-m-d H:i:s', TIMENOW - 900);

        $viewRows = [];
        foreach ($rows as $row) {
            $userRow = $userInfoArr->get($row['user'], User::defaultUser())->toArray();

            $avatar = ($curUser['avatars'] ?? false) ? \htmlspecialchars(trim((string) $userRow['avatar'])) : '';
            if ($avatar === '') {
                $avatar = 'pic/default_avatar.png';
            }

            $viewRows[] = new CommentRow(
                id: (int) $row['id'],
                userId: (int) $row['user'],
                author: UserDisplay::username((int) $row['user'], false, true, true, false, false, true),
                addedTime: SafeHtml::fromTrustedHtml((string) Time::format((string) $row['added'])),
                showViewOriginal: ! empty($row['editedby']) && $canManage,
                avatar: SafeHtml::fromTrustedHtml(UserDisplay::avatarImageWithContext($avatar)),
                text: SafeHtml::fromTrustedHtml(Format::formatComment((string) $row['text'])),
                editedBy: ! empty($row['editedby']) ? UserDisplay::username((int) $row['editedby']) : null,
                editedAt: ! empty($row['editedby']) ? SafeHtml::fromTrustedHtml((string) Time::format((string) $row['editdate'], true, false)) : null,
                online: ($userRow['last_access'] ?? '') > $dt,
                pmTitle: self::plainTitle('legacy/functions.title_send_message_to').(string) $userRow['username'],
                canDelete: $canManage,
                canEdit: (int) $row['user'] === (int) ($curUser['id'] ?? 0) || UserDisplay::currentClass() >= $commanageClass,
            );
        }

        return new CommentTableViewModel(
            $viewRows,
            $type,
            $parentId,
            $contentWidth,
            self::plainTitle('legacy/functions.title_report_this_comment'),
            self::plainTitle('legacy/functions.title_add_reply'),
        );
    }

    /**
     * Legacy lang values may carry `&nbsp;`-style entities; decoded to
     * plain text here so `{{ }}` escaping renders them correctly.
     */
    private static function plainTitle(string $key): string
    {
        return html_entity_decode((string) __($key), ENT_QUOTES | ENT_HTML401, 'UTF-8');
    }
}
