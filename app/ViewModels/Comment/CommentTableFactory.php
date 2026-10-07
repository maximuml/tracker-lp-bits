<?php

declare(strict_types=1);

namespace App\ViewModels\Comment;

use App\Auth\Permission;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Support\Avatar;
use App\Support\Cache\NexusCache;
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
        private readonly ?NexusCache $cache = null,
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
        UserDisplay::preload(array_merge($uidArr, array_filter(array_map('intval', array_column($rows, 'editedby')))));

        $canManage = Permission::can(PermissionEnum::COM_MANAGE);
        $dt = date('Y-m-d H:i:s', TIMENOW - 900);

        $renderedFmt = $this->cache !== null && $rows !== []
            ? $this->cache->getMany(array_map(static fn ($row) => 'fmt_comment_'.md5((string) $row['text']), $rows))
            : [];

        $viewRows = [];
        foreach ($rows as $row) {
            $userRow = $userInfoArr->get($row['user'], User::defaultUser())->toArray();

            $avatar = ($this->currentUser->value('avatars', false)) ? \htmlspecialchars(trim((string) ($userRow['avatar'] ?? ''))) : '';
            $avatar = Avatar::forUser((int) $row['user'], $avatar);

            $viewRows[] = new CommentRow(
                id: (int) $row['id'],
                userId: (int) $row['user'],
                author: UserDisplay::username((int) $row['user'], false, true, true, false, false, true),
                addedTime: SafeHtml::fromTrustedHtml((string) Time::format((string) $row['added'])),
                showViewOriginal: ! empty($row['editedby']) && $canManage,
                avatar: SafeHtml::fromTrustedHtml(UserDisplay::avatarImageWithContext($avatar)),
                text: $this->renderComment((string) $row['text'], $renderedFmt),
                editedBy: ! empty($row['editedby']) ? UserDisplay::username((int) $row['editedby']) : null,
                editedAt: ! empty($row['editedby']) ? SafeHtml::fromTrustedHtml((string) Time::format((string) $row['editdate'], true, false)) : null,
                online: ($userRow['last_access'] ?? '') > $dt,
                pmTitle: self::plainTitle('functions.title_send_message_to').(string) ($userRow['username'] ?? ''),
                canDelete: $canManage,
                canEdit: (int) $row['user'] === (int) ($this->currentUser->id()) || UserDisplay::currentClass() >= $commanageClass,
            );
        }

        return new CommentTableViewModel(
            $viewRows,
            $type,
            $parentId,
            $contentWidth,
            self::plainTitle('functions.title_report_this_comment'),
            self::plainTitle('functions.title_add_reply'),
        );
    }

    /**
     * @param  array<string, mixed>  $renderedFmt
     */
    private function renderComment(string $text, array &$renderedFmt): SafeHtml
    {
        $key = 'fmt_comment_'.md5($text);
        $hit = $renderedFmt[$key] ?? false;
        if (is_string($hit)) {
            return SafeHtml::fromTrustedHtml($hit);
        }
        $html = Format::formatComment($text);
        $this->cache?->put($key, (string) $html, 86400);
        $renderedFmt[$key] = (string) $html;

        return $html;
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
