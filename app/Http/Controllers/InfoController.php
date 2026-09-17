<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Repositories\InfoRepository;
use App\Services\BitbucketService;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Input;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Permissions;
use App\Support\Time;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InfoController extends LegacyController
{
    public function __construct(
        private readonly BitbucketService $bitbucketService,
        private readonly CurrentUser $currentUser,
        private readonly InfoRepository $infoRepository,
    ) {}

    public function userhistory(Request $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/userhistory.php'.($qs ? '?'.$qs : ''));
        }

        $userid = (int) request()->query('id');
        LegacyResponse::assertId($userid, true);

        $viewerId = (int) ($curUser['id'] ?? 0);
        if ($viewerId != $userid && ! Permissions::userCan(PermissionEnum::VIEW_USER_HISTORY->value, false, $viewerId)) {
            LegacyResponse::permissionDenied();
        }

        $action = htmlspecialchars((string) request()->query('action'));
        $perpage = 15;
        $phpSelf = Input::serverValue('PHP_SELF');
        $subject = UserDisplay::username($userid);

        $data = [
            'action' => $action,
            'userid' => $userid,
            'subject' => $subject,
            'title' => match ($action) {
                'viewposts' => (string) (__('legacy/userhistory.head_posts_history')),
                'viewcomments' => (string) (__('legacy/userhistory.head_comments_history')),
                default => (string) (__('legacy/userhistory.head_user_history')),
            },
        ];

        if ($action === 'viewposts') {
            $result = $this->infoRepository->getUserHistoryPosts($userid, (int) ($curUser['class'] ?? 0), $perpage, $phpSelf);
            if (empty($result['posts'])) {
                return $this->legacyAbortResponse((string) (__('legacy/userhistory.std_error')), (string) (__('legacy/userhistory.std_no_posts_found')));
            }
            $data = array_merge($data, $result);
            $data['items'] = $this->decorateHistoryPosts(
                (array) $result['posts'],
                (array) ($result['editorNames'] ?? []),
                $viewerId,
                $userid,
            );
        } elseif ($action === 'viewcomments') {
            $result = $this->infoRepository->getUserHistoryComments($userid, $perpage, $phpSelf);
            if (empty($result['comments'])) {
                return $this->legacyAbortResponse((string) (__('legacy/userhistory.std_error')), (string) (__('legacy/userhistory.std_no_comments_found')));
            }
            $data = array_merge($data, $result);
            $data['items'] = $this->decorateHistoryComments(
                (array) $result['comments'],
                (array) ($result['commentPageMap'] ?? []),
            );
        } elseif ($action === '') {
            return $this->legacyAbortResponse((string) (__('legacy/userhistory.std_history_error')), (string) (__('legacy/userhistory.std_unkown_action')), false);
        } else {
            return $this->legacyAbortResponse((string) (__('legacy/userhistory.std_history_error')), (string) (__('legacy/userhistory.std_unkown_action')));
        }

        return $this->legacyPage($request, 'userhistory', true, $data);
    }

    /**
     * @param  array<int|string, mixed>  $posts
     * @param  array<int|string, string>  $editorNames
     * @return list<array<string, mixed>>
     */
    private function decorateHistoryPosts(array $posts, array $editorNames, int $viewerId, int $userid): array
    {
        $items = [];
        foreach ($posts as $arr) {
            if (! is_array($arr)) {
                continue;
            }
            $body = Format::formatComment((string) ($arr['body'] ?? ''));
            $editedBy = $arr['editedby'] ?? 0;
            if (Validators::isId($editedBy) && ! empty($editorNames[(int) $editedBy])) {
                $body .= '<p><font size=1 class=small>'
                    .(string) (__('legacy/userhistory.text_last_edited'))
                    .UserDisplay::username((int) $editedBy)
                    .(string) (__('legacy/userhistory.text_at'))
                    .(string) ($arr['editdate'] ?? '')
                    .'</font></p>\n';
            }
            $items[] = [
                'added' => Time::format((string) ($arr['added'] ?? ''), true, false, false),
                'forumid' => (int) ($arr['f_id'] ?? 0),
                'forumname' => (string) ($arr['name'] ?? ''),
                'topicid' => (int) ($arr['t_id'] ?? 0),
                'topicname' => (string) ($arr['subject'] ?? ''),
                'postid' => (int) ($arr['id'] ?? 0),
                'isNew' => ((int) ($arr['lastpostread'] ?? 0) < (int) ($arr['lastpost'] ?? 0)) && $viewerId === $userid,
                'bodyHtml' => $body,
            ];
        }

        return $items;
    }

    /**
     * @param  array<int|string, mixed>  $comments
     * @param  array<int, int>  $commentPageMap
     * @return list<array<string, mixed>>
     */
    private function decorateHistoryComments(array $comments, array $commentPageMap): array
    {
        $items = [];
        foreach ($comments as $arr) {
            if (! is_array($arr)) {
                continue;
            }
            $commentId = (int) ($arr['id'] ?? 0);
            $torrent = (string) ($arr['name'] ?? '');
            if (strlen($torrent) > 55) {
                $torrent = substr($torrent, 0, 52).'...';
            }
            $commPage = (int) floor(($commentPageMap[$commentId] ?? 0) / 20);
            $items[] = [
                'added' => Time::format((string) ($arr['added'] ?? ''), true, false, false),
                'torrentid' => (int) ($arr['t_id'] ?? 0),
                'torrentName' => $torrent,
                'commentid' => $commentId,
                'pageUrl' => $commPage > 0 ? '&page='.$commPage : '',
                'bodyHtml' => Format::formatComment((string) ($arr['text'] ?? '')),
            ];
        }

        return $items;
    }

    public function donate(Request $request): View|RedirectResponse|Response
    {
        $data = $this->infoRepository->donationPageData();
        $data['thanks'] = $request->query('do') === 'thanks';

        return $this->legacyPage($request, 'donate', false, $data);
    }

    public function donated(Request $request): Response|RedirectResponse|View
    {
        $sysopClass = defined('UC_SYSOP') ? \constant('UC_SYSOP') : 0;
        if (UserDisplay::currentClass() < $sysopClass) {
            return $this->legacyAbortResponse('Sorry', 'Permission denied.');
        }

        $error = '';
        if ($request->isMethod('post')) {
            $username = trim((string) $request->input('username', ''));
            $donated = trim((string) $request->input('donated', ''));
            if ($username === '' || $donated === '') {
                $error = 'Missing form data.';
            } else {
                $user = User::query()->where('username', $username)->first(['id']);
                if (! $user) {
                    $error = 'Unable to update account.';
                } else {
                    User::query()->where('id', $user->id)->update(['donated' => $donated]);

                    return redirect('/userdetails.php?id='.$user->id);
                }
            }
        }

        return $this->legacyPage($request, 'donated', true, [
            'error' => $error,
        ]);
    }

    public function bitbucketlog(Request $request): Response|RedirectResponse|View
    {
        $currentUser = (array) ($this->currentUser->get() ?? []);
        $currentClass = (int) UserDisplay::currentClass();

        if ($currentClass < (defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0)) {
            return $this->legacyAbortResponse('Sorry', 'Access denied.');
        }

        $bucketPath = public_path('bitbucket');

        $delete = (int) $request->input('delete', 0);
        if ($currentClass >= (defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0) && $delete > 0) {
            if (! $request->isMethod('post')) {
                return $this->legacyAbortResponse('Error', 'Permission denied.');
            }
            $name = $this->bitbucketService->getBitbucketName($delete);
            $ok = $this->bitbucketService->deleteBitbucket($delete, $bucketPath);
            if (! $ok && $name !== null) {
                return $this->legacyAbortResponse('Warning', 'Unable to unlink file: <b>'.htmlspecialchars($name).'</b>. You should contact an administrator about this error.', false);
            }

            return redirect($request->url());
        }

        $count = (int) DB::table('bitbucket')->count();
        $perpage = 10;
        [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $count, 'bitbucketlog.php?');
        $bitbucketRows = DB::table('bitbucket')->orderByDesc('added')->offset($offset)->limit($perpage)->get();

        $userIds = [];
        $rows = [];
        foreach ($bitbucketRows as $row) {
            $arr = (array) $row;
            $rows[] = $arr;
            if ((int) ($arr['owner'] ?? 0) > 0) {
                $userIds[] = (int) $arr['owner'];
            }
        }

        UserDisplay::preload(array_values(array_unique($userIds)));
        $userDisplayMap = [];
        foreach (array_unique($userIds) as $uid) {
            $userDisplayMap[$uid] = UserDisplay::username($uid);
        }

        $imageDimensions = [];
        foreach ($rows as $row) {
            $file = $bucketPath.'/'.$row['name'];
            if (file_exists($file)) {
                $size = @getimagesize($file);
                $imageDimensions[$row['id']] = [
                    'width' => $size[0] ?? 0,
                    'height' => $size[1] ?? 0,
                ];
            } else {
                $imageDimensions[$row['id']] = ['width' => 0, 'height' => 0];
            }
        }

        $isModerator = $currentClass >= (defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0);
        $items = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            $name = (string) ($row['name'] ?? '');
            $owner = (int) ($row['owner'] ?? 0);
            $added = (string) ($row['added'] ?? '');
            $dim = $imageDimensions[$id] ?? ['width' => 0, 'height' => 0];
            $items[] = [
                'id' => $id,
                'name' => $name,
                'url' => str_replace(' ', '%20', htmlspecialchars("bitbucket/$name")),
                'date' => substr($added, 0, (int) strpos($added, ' ')),
                'time' => substr($added, (int) strpos($added, ' ') + 1),
                'width' => $dim['width'],
                'height' => $dim['height'],
                'usernameHtml' => (string) ($userDisplayMap[$owner] ?? UserDisplay::username($owner)),
            ];
        }

        return $this->legacyPage($request, 'bitbucketlog', true, [
            'items' => $items,
            'count' => $count,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'isModerator' => $isModerator,
        ]);
    }
}
