<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Models\Comment;
use App\Models\User;
use App\Repositories\CommentRepository;
use App\Repositories\MessageRepository;
use App\Support\Bonus;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Locale;
use App\Support\Url;
use Illuminate\Support\Facades\Gate;

/**
 * Comment posting pipeline extracted from WebCommentController so both
 * the legacy HTTP endpoints and the Livewire comment section share one
 * flow: authorize → flood check → insert → cache bust → owner PM → bonus.
 */
final class CommentService
{
    public function __construct(
        private readonly PermissionChecker $permissionChecker,
        private readonly MessageRepository $messageRepository,
        private readonly TorrentRepositoryInterface $torrentRepository,
        private readonly CommentRepository $commentRepository,
    ) {}

    public function post(User $user, string $type, int $parentId, string $body): int
    {
        $this->authorizeComment($user, $type, $parentId);
        $this->assertNotFlood($user);

        $parent = $this->commentRepository->getParent($parentId, $type);
        if (! $parent) {
            abort(404, __('legacy/comment.std_no_torrent_id'));
        }

        $newId = $this->commentRepository->create($parentId, $type, $body, (int) $user->id);
        $this->deleteCache($type, $parentId);
        $this->sendCommentPm($type, $parentId, (int) $parent['owner'], (string) $parent['name'], (int) $user->id);
        $this->applyBonus('+', (int) $user->id);

        return $newId;
    }

    public function authorizeComment(User $user, string $type, int $parentId): void
    {
        if ($user->parked) {
            abort(403, __('legacy/comment.std_permission_denied'));
        }

        if ($type === 'torrent') {
            $torrent = $this->torrentRepository->findById((int) $parentId);
            if (! $torrent) {
                abort(404, __('legacy/comment.std_no_torrent_id'));
            }
            Gate::authorize('comment', $torrent);
        }
    }

    public function assertNotFlood(User $user): void
    {
        if ($this->permissionChecker->userCan('commanage', false, (int) $user->id)) {
            return;
        }

        $lastComment = $user->last_comment;
        if ($lastComment === null || $lastComment === '') {
            return;
        }

        $ts = strtotime((string) $lastComment);
        if ($ts === false || $ts <= (TIMENOW - 10)) {
            return;
        }

        $secs = 10 - (TIMENOW - $ts);
        abort(403, __('legacy/comment.std_comment_flooding_denied').$secs.__('legacy/comment.std_before_posting_another'));
    }

    public function deleteCache(string $type, int $parentId): void
    {
        Cache::forgetWithLocales($type.'_'.$parentId.'_last_comment_content');
    }

    public function applyBonus(string $sign, int $userId): void
    {
        $points = SiteConfig::current()->bonus->addComment();
        if ($points != 0) {
            Bonus::updatePoints($sign, $points, $userId);
        }
    }

    public function sendCommentPm(string $type, int $parentId, int $ownerId, string $name, int $commenterId): void
    {
        if ($ownerId === $commenterId) {
            return;
        }

        if (! $this->commentRepository->getCommentPmSetting($ownerId)) {
            return;
        }

        $locale = Locale::userLocale($ownerId);
        $subject = Locale::trans('comment.msg_new_comment', [], $locale);
        $messageKey = 'comment.msg_'.$type.'_receive_comment';
        $message = Locale::trans($messageKey, [], $locale)
            .' [url='.Url::siteBase().'/'.$this->buildScript($type, $parentId).'] '.$name.'[/url].';

        $this->messageRepository->add([
            'sender' => null,
            'receiver' => $ownerId,
            'subject' => $subject,
            'added' => now(),
            'msg' => $message,
        ]);
    }

    public function buildScript(string $type, int $parentId): string
    {
        $script = Comment::TYPE_MAPS[$type]['target_script'] ?? '/web/details/%s';

        return sprintf($script, $parentId);
    }
}
