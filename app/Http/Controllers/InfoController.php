<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Repositories\InfoRepository;
use App\Support\CurrentUser;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InfoController extends LegacyController
{
    public function userhistory(Request $request): View|RedirectResponse|Response
    {
        $curUser = app(CurrentUser::class)->get();
        if ($curUser === null) {
            $qs = $request->getQueryString();

            return redirect('/userhistory.php'.($qs ? '?'.$qs : ''));
        }

        $userid = (int) SupportContext::getQuery('id');
        LegacyResponse::assertId($userid, true);

        $viewerId = (int) ($curUser['id'] ?? 0);
        if ($viewerId != $userid && ! Permissions::userCan(PermissionEnum::VIEW_USER_HISTORY->value, false, $viewerId)) {
            LegacyResponse::permissionDenied();
        }

        $action = htmlspecialchars((string) SupportContext::getQuery('action'));
        $perpage = 15;
        $phpSelf = SupportContext::getServerValue('PHP_SELF');
        $subject = UserDisplay::username($userid);

        $data = [
            'action' => $action,
            'userid' => $userid,
            'subject' => $subject,
        ];

        if ($action === 'viewposts') {
            $data = array_merge($data, InfoRepository::getUserHistoryPosts($userid, (int) ($curUser['class'] ?? 0), $perpage, $phpSelf));
        } elseif ($action === 'viewcomments') {
            $data = array_merge($data, InfoRepository::getUserHistoryComments($userid, $perpage, $phpSelf));
        }

        return $this->legacyPage($request, 'userhistory', true, $data);
    }

    public function donate(Request $request): View|RedirectResponse|Response
    {
        $data = InfoRepository::donationPageData();
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
        $currentUser = (array) (app(CurrentUser::class)->get() ?? []);
        $currentClass = (int) UserDisplay::currentClass();

        if ($currentClass < (defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0)) {
            return $this->legacyAbortResponse('Sorry', 'Access denied.');
        }

        $bucketPath = public_path('bitbucket');

        $delete = (int) $request->input('delete', 0);
        if ($currentClass >= (defined('UC_MODERATOR') ? \constant('UC_MODERATOR') : 0) && $delete > 0) {
            $bitbucket = DB::table('bitbucket')->where('id', $delete)->first(['name', 'owner']);
            if ($bitbucket) {
                $file = $bucketPath.'/'.$bitbucket->name;
                DB::table('bitbucket')->where('id', $delete)->delete();
                if (file_exists($file) && ! unlink($file)) {
                    return $this->legacyAbortResponse('Warning', 'Unable to unlink file: <b>'.htmlspecialchars((string) $bitbucket->name).'</b>. You should contact an administrator about this error.', false);
                }
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

        return $this->legacyPage($request, 'bitbucketlog', true, [
            'rows' => $rows,
            'count' => $count,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'userDisplayMap' => $userDisplayMap,
            'imageDimensions' => $imageDimensions,
        ]);
    }
}
